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
    const VERSION = '0.2.2 beta';

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

    /**
     * @var int 随机延迟至此时间
     */
    public $delayTime = 0;

    public function __construct($session = null)
    {
        $this->config = config('weChat');

        if ($session) {
            $this->config['session'] = $session;
        }

        // 实例化独立的curl客户端
        $this->client = new Client([
            'headers' => [
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip;q=1.0, compress;q=0.5',
                'Accept-Language' => 'zh-Hans-CN;q=1.0, zh-Hant-CN;q=0.9, ja-CN;q=0.8',
                'Connection' => 'keep-alive',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36',
                'Referer' => '',
            ],
            'timeout' => 30.0,
            'http_errors' => false,
            'cookies' => true,
        ]);
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
        /*$messageHandler->setCustomHandler(function () {
            $now = time();

            // 每晚八点后更新
//            if ($now <= strtotime('20:00')) {
//                return;
//            }

            // 随机延迟，模拟真人
            if ($now < $this->delayTime) {
                return;
            } else {
                $this->delayTime = $now + mt_rand(5, 10) * 60;
                Log::notice(sprintf('触发随机延迟，今次请求后，将在%s后再次发起请求', date('Y-m-d H:i:s', $this->delayTime)));
            }

            $this->qyn();
        });*/

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

                // TODO 处理消息撤回，vbot封装的方法已失效
                /*if ($message['type'] === 'recall') {
                    Text::send('filehelper', $message['content'] . ' : ' . $message['origin']['content']);
                    if ($message['origin']['type'] === 'image') {
                        Image::send('filehelper', $message['origin']);
                    } elseif ($message['origin']['type'] === 'emoticon') {
                        Emoticon::send('filehelper', $message['origin']);
                    } elseif ($message['origin']['type'] === 'video') {
                        Video::send('filehelper', $message['origin']);
                    } elseif ($message['origin']['type'] === 'voice') {
                        Voice::send('filehelper', $message['origin']);
                    }
                }*/
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
}