<?php
/**
 * 推送
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 17:46
 */

namespace Luolongfei\App\Console;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Luolongfei\Lib\Base;
use Luolongfei\Lib\Log;
use Luolongfei\Lib\Curl;
use Luolongfei\Lib\Mail;
use Luolongfei\Lib\CatDiscount;

use Hanson\Vbot\Foundation\Vbot as Robot;
use Luolongfei\Lib\MysqlPDO;
use Luolongfei\Lib\POE;
use Vbot\Blacklist\Blacklist;
use Vbot\GuessNumber\GuessNumber;
use Vbot\HotGirl\HotGirl;
use Hanson\Vbot\Message\Text;
use Illuminate\Support\Collection;
use Hanson\Vbot\Message\Image;
use Hanson\Vbot\Message\Emoticon;
use Hanson\Vbot\Message\Video;
use Hanson\Vbot\Message\Voice;

use Luolongfei\Lib\Redis;

class Pusher extends Base
{
    /**
     * 版本号
     */
    const VERSION = '0.3.0 beta';

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

    /**
     * @var Client 独立的curl客户端，便于走代理
     */
    public $client;

    public function __construct($session = null)
    {
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

    /**
     * @return bool
     * @throws \Hanson\Vbot\Exceptions\ArgumentException
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
            if (is_locked('notice_task') || time() <= strtotime('06:30')) { // 每天一次，06:30提醒
                return;
            }

            $friends = vbot('friends');
            $friend = $friends->getUsernameByRemarkName(env('GIRLFRIEND_REMARK_NAME'), false);

            /**
             * 随机诗词
             */
            $retry = 0;
            while (true) {
                try {
                    if ($retry > config('maxRetry')) {
                        break;
                    }

                    /*$poetryApi = [
                        'shuqing/aiqing',
                        'shuqing/sinian',
                        'rensheng/lizhi',
                    ];*/
                    $poetry = Curl::get('https://v1.jinrishici.com/all.json');
                    $poetry = json_decode($poetry, true);

                    $poetryContent = sprintf(
                        "「%s」\n\n摘自 %s《%s》",
                        $poetry['content'],
                        $poetry['author'],
                        $poetry['origin']
                    );

                    if ($this->poetryCheck($poetryContent)) {
                        break;
                    }

                    $retry++;

                    Log::info(sprintf('检出低质量诗词，已丢弃并重新获取。重试次数：%d', $retry));
                } catch (\Exception $e) {
                    Log::error('获取随机诗词出错：' . $e->getMessage());
                }

                sleep(1);
            }

            /*try {
                Text::send($friend, POE::getPoetry());
            } catch (\Exception $e) {
                Log::error('发送或获取诗歌文摘出错：' . $e->getMessage());
            }*/

            // 倒数
            for ($i = config('countdown'); $i > 0; $i--) {
                Text::send($friend, $i);
                sleep(1);
            }

            $content = sprintf("%s起床啦，该开始学习了，今天是发奋的第%s\n\n%s", $this->getEmoji(), $this->stat(), $poetryContent);
            $rt = Text::send($friend, $content);

            if ($rt === false) {
                Log::error('消息发送失败');
                Mail::send('主人，消息推送失败', "消息内容：\n" . $content);
            }

            lock_task('notice_task');

            usleep(500000);
        });

        // 收到消息时触发
        $messageHandler->setHandler(function (Collection $message) {
            try {
                // 仅处理好友来信
                if ($message['fromType'] === 'Friend') {
                    // TODO 正则检查是否回复的1或降价提醒，如果是，通过发送者的username去redis查询，若有数据，将status改为1，过期时间改为永久
                    // TODO 新加的batch，常驻专门读取redis中status=1的数据，拿url取得最新价格与现有价格做对比，低于现有价格，就给username发微信

                    // 检查是否商品地址
                    $url = CatDiscount::goodsUrlCheck($message['message']);
                    if ($url) {
                        // 获取价格文言
                        $priceText = CatDiscount::getPriceText($url);

                        // 原路返回
                        $username = $message['fromType'] === 'Self' ? 'filehelper' : $message['from']['UserName'];
                        Text::send($username, $priceText);

                        /*if (CatDiscount::$success) { // 正确返回了价格文言
                            $token = md5(uniqid(microtime() . mt_rand(), true));
                            $allData = CatDiscount::$allData;

                            // 缓存商品价格信息
                            Redis::setex($token, config('urlTtl'), json_encode($allData));

                            // 价格走势截图
                            $imgName = sprintf('%s.png', $token);
                            $imgPath = RESOURCES_PATH . '/screenshot/';
                            $imgFile = $imgPath . $imgName;
                            $cmd = sprintf(
                                'node %s/screenshot.js --url=https://llf.design/price/%s --save_path=%s --name=%s',
                                NODEJS_PATH,
                                $token,
                                $imgPath,
                                $imgName
                            );
                            $cmdRt = shell_exec($cmd);
                            Log::info('ChromeHeadless截图执行回显：' . $cmdRt);

                            // 发送价格变动图片
                            if (file_exists($imgFile)) {
                                Image::send($username, $imgFile);
                                Log::info(sprintf('截图文件%s删除%s', $imgFile, unlink($imgFile) ? '成功' : '失败'));
                            }
                        }*/

                        // TODO 保存数据到redis 以username作为键（若已存在则直接覆盖，且过期时间延长至2小时）
                        // TODO 数据内容为username，url，currPrice，时间戳，status（0或1，1代表需要降价提醒的任务）   2小时过期
                    }
                } else if ($message['fromType'] === 'Self') {
                    // TODO 处理自己的命令
                }
            } catch (\Exception $e) {
                Log::error('收到消息处理时发生错误: ' . $e->getMessage());
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
    public function poetryCheck($poetry = '', $rules = [])
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
     * 随机获取表情
     *
     * @param int $multiplier 重复次数
     *
     * @return string
     */
    public function getEmoji($multiplier = 2)
    {
        $mood = [
            '[嘿哈]',
            '[捂脸]',
            '[奸笑]',
            '[机智]',
            '[皱眉]',
            '[耶]',
            '[吃瓜]',
            '[加油]',
            '[汗]',
            '[天啊]',
            '[Emm]',
            '[社会社会]',
            '[旺柴]',
            '[好的]',
            '[打脸]',
            '[哇]',
            '[呲牙]',
            '[害羞]',
            '[愉快]',
            '[白眼]',
            '[困]',
            '[囧]',
            '[惊恐]',
            '[流汗]',
            '[憨笑]',
            '[悠闲]',
            '[奋斗]',
            '[嘘]',
            '[晕]',
            '[敲打]',
            '[抠鼻]',
            '[鼓掌]',
            '[坏笑]',
            '[左哼哼]',
            '[右哼哼]',
            '[哈欠]',
            '[阴险]',
            '[可怜]',
            '[猪头]',
            '[发抖]',
            '[转圈]',
            '[跳跳]',
        ];

        $emoji = $mood[mt_rand(0, count($mood) - 1)];

        return str_repeat($emoji, $multiplier);
    }

    /**
     * 统计距离某天过去了多久
     *
     * @param string $date
     * @param string $timeType m:month | h:hour | d:day
     *
     * @return string
     */
    public function stat($date = '', $timeType = 'd')
    {
        $date = $date ?: config('WORK_HARD_DATE_START');
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