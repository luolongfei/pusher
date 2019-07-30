<?php
/**
 * 散文诗歌
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/7/27
 * @time 20:09
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Base;
use Luolongfei\Lib\Curl;

class POE extends Base
{
    const POE_API_URL = 'http://poe.mionapp.com/poe/index.php';

    /**
     * 模拟POE客户端
     */
    const POE_IOS_APP = [
        'headers' => [
            'Accept-Language' => 'zh-Hans-CN;q=1, zh-Hant-CN;q=0.9, ja-CN;q=0.8',
            'User-Agent' => 'poe/1.6.2 (iPhone; iOS 12.4; Scale/3.00)',
        ]
    ];

    /**
     * @var array 认证cookie
     */
    protected static $poeCookies;

    /**
     * @var POE
     */
    protected static $instance;

    public static function getInstance()
    {
        if (!self::$instance instanceof POE) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 随机获取一首诗歌或者文摘
     *
     * @return string
     * @throws \Exception
     */
    public static function getPoetry()
    {
        // 获取诗歌ID组
        $ids = self::getPoetryIds();

        // 随机取一个诗歌ID
//        $id = $ids[mt_rand(0, count($ids) - 1)];

        foreach ($ids as $id) {
            $response = Curl::get(
                self::POE_API_URL,
                [
                    'controller' => 'poem',
                    's' => $id
                ],
                self::POE_IOS_APP
            );
            $response = json_decode($response, true);

            if (!isset($response[0]) || !isset($response[0]['response']) || $response[0]['response'] !== 'success') {
                throw new \Exception('获取诗歌出错，今次响应内容为：' . json_encode($response));
            }
            $rt = $response[0];
            $artist = $rt['artist'];
            $content = str_ireplace('|^n|', "\n", $rt['content']);
            $title = $rt['title'];

            if (preg_match('/(?:[A-Za-z]+|少有人走的路|·)/iu', $title . $artist)) { // 过滤英文翻译的诗歌，这类质量低的令人发指
                sleep(1);
                continue;
            }

            return sprintf(
                "%s\n\n摘自 %s《%s》",
                $content,
                $artist,
                $title
            );
        }

        throw new \Exception('获取诗歌ID组出错');
    }

    /**
     * 获取诗歌ID组
     *
     * @return array
     * @throws \Exception
     */
    private static function getPoetryIds()
    {
        $response = Curl::get(
            self::POE_API_URL,
            [
                'controller' => 'poem',
                'action' => 'getcount'
            ],
            self::POE_IOS_APP
        );
        $response = json_decode($response, true);

        if (!isset($response[0]['response']) || $response[0]['response'] !== 'success') {
            throw new \Exception('获取诗歌ID组出错，今次响应内容为：' . json_encode($response));
        }

        $ids = explode('^', $response[0]['count']);

        return $ids;
    }
}