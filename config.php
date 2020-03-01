<?php
/**
 * 配置
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/2
 * @time 11:39
 */

return [
    'debug' => false,
    'stdout' => false,

    /**
     * 邮箱配置
     */
    'mail' => [
        /**
         * 目前机器人邮箱账户支持谷歌邮箱、QQ邮箱以及163邮箱，程序会自动判断填入的邮箱类型。注意，QQ邮箱与163邮箱均使用账户加授权码的方式
         * 登录，谷歌邮箱使用账户加密码的方式登录，请知悉。
         */
        'to' => env('TO'), // 接收通知的邮箱
        'toName' => '主人', // 收件人名字
        'username' => env('MAIL_USERNAME'), // 机器人邮箱账户
        'password' => env('MAIL_PASSWORD'), // 机器人邮箱密码或授权码
        'debug' => 0, // debug，当邮件无法发送的情况下开启此项观察命令行界面提示信息，正式环境应关闭 0：关闭 1：客户端信息 2：客户端和服务端信息

        // 'replyTo' => 'mybsdc@qq.com', // 接收回复的邮箱
        // 'replyToName' => '作者', // 接收回复的人名
    ],

    /**
     * 数据库配置
     */
    'database' => [
        'mysql' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0)
        ],
    ],

    'urlTtl' => 3600 * 2, // 缓存有效期2小时
    'qynTtl' => 3600 * 24 * 30, // 庆余年有效期1个月

    /**
     * 价格折线截图宽高
     */
    'width' => 1366,
    'height' => 768,

    /**
     * 指定关键字，用于过滤低质量诗词
     */
    'lowQualityKeywords' => [
        '贫贱',
        '妇人',
        '死',
        '坟',
        '冢',
        '黄土',
        '棺',
    ],

    // 诗词最大重试次数
    'maxRetry' => 2020,

    // 开始发奋图强
    'WORK_HARD_DATE_START' => '2020-02-28',

    /**
     * 匹配商城URL的正则
     */
    'goodsUrlRegex' => [
        '京东' => 'https?:\/\/.*?jd\.(?:com|hk).*?(?:\.html|(?:(?=[\?\s])|$))',
        '天猫|淘宝' => 'https?:\/\/(?:.*?tb\.cn.*?(?:(?=[\?\s])|$|(?=[^\x00-\xff]))|.*?(?:tmall\.com|taobao\.com).*?id=.*?(?:(?=[&\s])|$|(?=[^\x00-\xff])))|[¢\$₴₳€₤￥].*?[¢\$₴₳€₤￥]|.*?[綯淘].*?[寳宝宀]|手机天猫',
        '唯品会' => 'https?:\/\/.*?vip\.com.*?\.html',
        /*'当当' => 'https?:\/\/.*?dangdang\.com.*?(?:\.html|pid=.*?)(?:(?=[&\s<\?])|$|(?=[^\x00-\xff]))',
        '亚马逊' => 'https?:\/\/.*?amazon\.cn.*?(?:(?=[\?\s<;&])|$)',*/
    ],

    /**
     * 微信机器人配置
     */
    'weChat' => [
        'path' => ROOT_PATH . '/logs/WeChat/',

        /*
         * swoole 配置项，执行主动发消息命令必须开启
         */
        'swoole' => [
            'status' => false,
            'ip' => '127.0.0.1',
            'port' => '8866',
        ],

        /*
         * 下载配置项
         */
        'download' => [
            'image' => false,
            'voice' => false,
            'video' => false,
            'emoticon' => true,
            'file' => false,
            'emoticon_path' => ROOT_PATH . '/logs/WeChat/emoticons/',
        ],

        /*
         * 输出配置项
         */
        'console' => [
            'output' => true, // 是否输出
            'message' => true, // 是否输出接收消息 （若上面为 false 此处无效）
        ],

        /*
         * 日志配置项
         */
        'log' => [
            'level' => 'debug',
            'permission' => 0777,
            'system' => ROOT_PATH . '/logs/WeChat/log',
            'message' => ROOT_PATH . '/logs/WeChat/log',
        ],

        /*
         * 缓存配置项
         */
        'cache' => [
            'default' => 'file',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => ROOT_PATH . '/logs/WeChat/cache',
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ],
        ],
        'database' => [
            'redis' => [
                'client' => 'predis',
                'default' => [
                    'host' => '127.0.0.1',
                    'password' => null,
                    'port' => 6379,
                    'database' => 13,
                ],
            ],
        ],

        /*
         * 拓展配置
         */
        'extension' => [
            'admin' => [
                'remark' => '',
                'nickname' => 'mybsdc',
            ],
        ],
    ],
];