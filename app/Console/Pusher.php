<?php
/**
 * 推送
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 17:46
 */

namespace Luolongfei\App\Console;

use Luolongfei\Lib\Base;
use Luolongfei\Lib\Log;
use Luolongfei\Lib\Curl;
use Luolongfei\Lib\Mail;
use Luolongfei\Lib\CatDiscount;

use Hanson\Vbot\Foundation\Vbot as Robot;
use Luolongfei\Lib\MysqlPDO;
use Vbot\Blacklist\Blacklist;
use Vbot\GuessNumber\GuessNumber;
use Vbot\HotGirl\HotGirl;
use Hanson\Vbot\Message\Text;
use Illuminate\Support\Collection;
use Hanson\Vbot\Message\Image;
use Hanson\Vbot\Message\Emoticon;
use Hanson\Vbot\Message\Video;
use Hanson\Vbot\Message\Voice;

use Luolongfei\Lib\POE;

class Pusher extends Base
{
    /**
     * MEET_DATE
     */
    protected static $MEET_DATE;

    /**
     * LOVE_DATE_START
     */
    protected static $LOVE_DATE_START;

    /**
     * @var Pusher
     */
    protected static $instance;

    /**
     * @var Robot
     */
    protected static $robot;

    /**
     * @var array|mixed 微信机器人配置
     */
    private $config;

    public function __construct($session = null)
    {
        self::$MEET_DATE = config('meetDate');
        self::$LOVE_DATE_START = config('loveDateStart');

        $this->config = config('weChat');

        if ($session) {
            $this->config['session'] = $session;
        }
    }

    /**
     * @return Pusher
     */
    public static function instance()
    {
        if (!self::$instance instanceof Pusher) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getWeek()
    {
        $week = ['天', '一', '二', '三', '四', '五', '六']; // 0（表示星期天）到 6（表示星期六）

        return '星期' . $week[date('w')];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function handle()
    {
        $weChat = self::robotInstance($this->config);

        // 获取监听器实例
        $observer = $weChat->observer;

        // 获取消息处理器实例
        $messageHandler = $weChat->messageHandler;

        // 一直触发
        $messageHandler->setCustomHandler(function () {
//            Log::info('触发执行，心跳检出');
            $friends = vbot('friends');
            $friend = $friends->getUsernameByRemarkName(config('girlfriendRemarkName'), false);

            foreach (config('classes.' . date('w')) as $time => $class) { // 只遍历当天的课程
                $fileName = str_replace(':', '_', $time);
                if (is_repeated($fileName)) {
                    usleep(500000);
                    continue;
                }

                $startTime = strtotime($time); // 仅传时分，默认为当天日期
                $now = time();

                if ($now <= $startTime && ($startTime - $now) <= config('inAdvance') && $class) { // 提前几分钟推送
                    $num = 0;
                    while (true) {
                        try {
                            // 随机诗词
                            $poetryApi = [
                                'shuqing/aiqing',
                                'shuqing/sinian',
                                'rensheng/lizhi',
                            ];
                            $poetry = Curl::get(sprintf('https://api.gushi.ci/%s.json', $poetryApi[mt_rand(0, count($poetryApi) - 1)]));
                            $poetry = json_decode($poetry, true);

                            $poetrySummary = '';
                            $poetryContent = isset($poetry['content']) ? $poetry['content'] : '';
                            if (!$poetryContent) {
                                throw new \Exception('诗词接口返回的数据异常');
                            }

                            $poetrySummary = sprintf(
                                "%s",
                                $poetryContent
                            );

                            if (self::poetryCheck($poetrySummary)) {
                                break;
                            }

                            $num++;
                            Log::info(sprintf('检出低质量诗词，拼接内容为：%s  处理：已丢弃并重新获取。重试次数：%d', $poetrySummary, $num));

                            sleep(1);
                        } catch (\Exception $e) {
                            Log::error('获取随机诗词出错：' . $e->getMessage());
                            break;
                        }
                    }

                    $content = '';
                    if (strlen($class) && $class[0] === '#') { // 说晚安
                        $content .= str_replace('#', '', $class);

                        // 先念一份诗歌或者文摘
                        try {
                            Text::send($friend, POE::getPoetry());
                        } catch (\Exception $e) {
                            Log::error('发送或获取诗歌文摘出错：' . $e->getMessage());
                        }
                    } else {
                        list($minute, $second) = explode('.', bcdiv($startTime - time(), 60, 2));
                        $second = bcmul('0.' . $second, 60);

                        $content .= $class === '午睡' ? '该睡告告了。' : sprintf(
                            '该上「%s」课啦，距上课还有%s分%s秒。',
                            $class,
                            $minute < 0 ? 0 : $minute,
                            $second < 10 ? '0' . $second : $second
                        );
                    }

                    $content .= sprintf(
                        "\n\n今天是我们相识的第%s（第%s），正式相爱的第%s（第%s），想你的第%s~[爱心]\n\n%s",
                        self::LOVE(self::$MEET_DATE, 'd'),
                        self::LOVE(self::$MEET_DATE, 'm'),
                        self::LOVE(self::$LOVE_DATE_START, 'd'),
                        self::LOVE(self::$LOVE_DATE_START, 'm'),
                        self::LOVE(self::$LOVE_DATE_START, 'h'),
                        $poetrySummary
                    );

                    $rt = Text::send($friend, $content);

                    if ($rt === false) {
                        Log::error('消息发送失败');
                        Mail::send('主人，消息推送失败', "消息内容：\n" . (string)$content);
                    }

                    create_file($fileName, $rt);
                }
            }

            usleep(500000); // 防止执行过快，内存占用过高
        });

        // 收到消息时触发
        $messageHandler->setHandler(function (Collection $message) {
            try {
                // 仅处理好友来信
                if ($message['fromType'] === 'Friend') {
                    // TODO 正则检查是否回复的1或降价提醒，如果是，通过发送者的username去redis查询，若有数据，将status改为1，过期时间改为永久
                    // TODO 新加的batch，常驻专门读取redis中status=1的数据，拿url取得最新价格与现有价格做对比，低于现有价格，就给username发微信


                    /*if (preg_match('/^(?:1+|降价提醒)$/', $message['message'])) {

                    }*/

                    // 检查是否商品地址
                    $url = CatDiscount::shopUrlCheck($message['message']);
                    if ($url) {
                        // 获取价格文言
                        $priceText = CatDiscount::getPriceText($url);

                        // 原路返回
                        Text::send($message['from']['UserName'], $priceText);

                        // TODO 保存数据到redis 以username作为键（若已存在则直接覆盖，且过期时间延长至2小时）
                        // TODO 数据内容为username，url，currPrice，时间戳，status（0或1，1代表需要降价提醒的任务）   2小时过期
                    }
                } else if ($message['fromType'] === 'Self') {
                    // TODO 处理自己的命令
                }
            } catch (\Exception $e) {
                Log::error('收到消息处理时发生错误: ', $e->getMessage());
            }
        });

        /**
         * 免扫码成功监听器
         */
        $observer->setReLoginSuccessObserver(function () {
            Log::info('免扫码登录成功');
            Mail::send('主人，免扫码登录成功，服务已恢复', '免扫码登录成功，说明服务已经恢复，不用再扫码登录了。');
        });

        /**
         * 二维码监听器
         * 在登录时会出现二维码需要扫码登录。而这个二维码链接也将传到二维码监听器中。
         */
        $observer->setQrCodeObserver(function ($qrCodeUrl) {
//            Log::info($qrCodeUrl);
        });

        /**
         * 程序退出监听器
         */
        $observer->setExitObserver(function () {
            Log::info('微信机器人被挂起，已退出');
            Mail::send('主人，微信机器人被挂起，已退出', '微信机器人被挂起，已退出，可能需要重新扫码登录。请登录服务器确认具体情况。');
        });

        /**
         * 异常监听器
         * 当接收消息异常时，当系统判断为太久没从手机端打开微信时，则急需打开，时间过久将断开。
         */
        $observer->setNeedActivateObserver(function () {
            Log::info('太久没从手机端打开微信，急需打开，时间过久将断开');
            Mail::send('主人，太久没从手机端打开微信，急需打开，时间过久将断开', '太久没从手机端打开微信，急需打开，时间过久将断开。快打开手机上的微信。');
        });

        $weChat->server->serve();

        /*$startHandleTime = time();

        while (true) {
            if ((time() - $startHandleTime) > 1800) { // 每次循环半小时，挂起后由Supervisor重新拉起
                break;
            }

            foreach (config('classes.' . date('w')) as $timeRange => $class) { // 只遍历当天的课程
                $date = date('Y-m-d');
                list($start, $end) = explode('-', $timeRange);

                $fileName = str_replace(':', '_', $start);
                if (is_repeated($fileName)) {
                    usleep(500000);
                    continue;
                }

                $startTime = strtotime($date . ' ' . $start);
                $endTime = strtotime($date . ' ' . $end);
                $now = time();

                if ($now <= $startTime && ($startTime - $now) <= config('inAdvance') && $class) { // 提前几分钟推送
                    try {
                        // 随机诗词
                        $poetryApi = [
                            'shuqing/aiqing',
                        ];
                        $poetry = Curl::get(sprintf('https://api.gushi.ci/%s.json', $poetryApi[mt_rand(0, count($poetryApi) - 1)]));
                        $poetry = json_decode($poetry, true);

                        $poetrySummary = '';
                        $poetryContent = isset($poetry['content']) ? $poetry['content'] : '';
                        if (!$poetryContent) {
                            throw new \Exception('诗词接口返回的数据异常');
                        }

                        $poetrySummary = sprintf(
                            "诗词取自%s写的《%s》, 分类于「%s」之下。\n\n%s\n\n报告完毕。阿姨开始上课吧啦啦啦~",
                            $poetry['author'],
                            $poetry['origin'],
                            $poetry['category'],
                            $poetryContent
                        );
                    } catch (\Exception $e) {
                        Log::error('获取随机诗词出错：' . $e->getMessage());
                    }

                    list($minute, $second) = explode('.', bcdiv($startTime - time(), 60, 2));
                    $second = bcmul('0.' . $second, 60);

                    $title = sprintf(
                        '阿姨，该上「%s」课啦，距上课还有%s分%s秒。',
                        $class,
                        $minute < 0 ? 0 : $minute,
                        $second < 10 ? '0' . $second : $second
                    );
                    $content = sprintf(
                        "今天是相识的第%s天，正式相爱的第%s天，第%s个小时。师父正在\n想你~\n%s\nby 师父",
                        self::LOVE(self::$MEET_DATE),
                        self::LOVE(),
                        self::LOVE(self::$LOVE_DATE_START, 'h'),
                        $poetrySummary
                    );

                    $rt = ServerChan::send($title, $content);
                    create_file($fileName, $rt);
                }
            }

            usleep(500000); // 防止执行过快，内存占用过高
        }*/

        return true;
    }

    public static function robotInstance($config)
    {
        if (static::$robot === null) {
            static::$robot = new Robot($config);
        }

        return static::$robot;
    }

    /**
     * 检查过滤低质量诗词
     *
     * 当诗词内容存在rules里指定的关键字时，表示检查不通过，返回false，否则返回true。支持正则
     *
     * @param string $poetry
     * @param array $rules
     *
     * @return bool
     */
    public static function poetryCheck($poetry = '', $rules = [])
    {
        $rules = $rules ?: config('lowQualityKeywords');
        if (empty($rules)) {
            return true;
        }

        $regex = '';
        foreach ($rules as $rule) {
            if (strlen($rule) === 0) {
                continue;
            }
            $regex .= '|' . $rule;
        }
        if ($regex === '') {
            return true;
        }
        $regex = sprintf('/(?:%s)/i', ltrim($regex, '|'));

        return preg_match($regex, $poetry) === 0;
    }

    /**
     * 恋爱日期获取
     *
     * @param string $date
     * @param string $timeType m:month|h:hour|d:day
     *
     * @return string
     */
    public static function LOVE($date = '', $timeType = 'd')
    {
        $date = $date ?: self::$LOVE_DATE_START;
        $start = strtotime($date);

        $time = '无穷大';
        switch ($timeType) {
            case 'm':
                $monthNum = (date('Y') - date('Y', $start)) * 12 + (date('n') - date('n', $start));
                $time = sprintf('%d个月', $monthNum);
                break;
            case 'd':
                $time = sprintf('%d天', ceil((time() - $start) / (24 * 3600)));
                break;
            case 'h':
                $time = sprintf('%d个小时', ceil((time() - $start) / 3600));
                break;
        }

        return $time;
    }
}