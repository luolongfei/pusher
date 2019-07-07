<?php
/**
 * Curl
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 12:41
 */

namespace Luolongfei\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Luolongfei\Lib\Log;

class Curl
{
    /**
     * @var Curl
     */
    protected static $instance;

    /**
     * @var float 超时
     */
    public static $timeout = 30.0;

    /**
     * @var CookieJar
     */
    protected static $jar;

    /**
     * GET请求
     *
     * @param $url
     * @param array $params query参数
     * @param array $opt 指定参数
     *
     * @return string
     * @throws \Exception
     */
    public static function get($url, $params = [], $opt = [])
    {
        $payload = [
            'cookies' => self::jar(),
        ];
        if ($params) {
            $payload['query'] = $params;
        }
        if ($opt) {
            $payload = array_merge($payload, $opt);
        }

        $request = self::client()->get($url, $payload);
        $body = (string)$request->getBody();

        Log::debug(sprintf('GET请求：%s，返回：%s', $url, $body), $payload);

        return $body;
    }

    /**
     * POST请求
     *
     * @param $url
     * @param array $params 表单字段
     * @param array $opt 指定参数
     *
     * @return string
     * @throws \Exception
     */
    public static function post($url, $params = [], $opt = [])
    {
        $payload = [
            'cookies' => self::jar(),
        ];
        if ($params) {
            $payload['form_params'] = $params;
        }
        if ($opt) {
            $payload = array_merge($payload, $opt);
        }

        $request = self::client()->post($url, $payload);
        $body = (string)$request->getBody();

        Log::debug(sprintf('POST请求：%s，返回：%s', $url, $body), $payload);

        return $body;
    }

    /**
     * @return CookieJar
     */
    public static function jar()
    {
        if (!self::$jar instanceof CookieJar) {
            self::$jar = new CookieJar();
        }

        return self::$jar;
    }

    /**
     * @return Client
     */
    public static function client()
    {
        if (!self::$instance instanceof Client) {
            $options = [
                'headers' => [ // 默认header，此处header中同名项可被覆盖
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip;q=1.0, compress;q=0.5',
                    'Accept-Language' => 'zh-Hans-CN;q=1.0, zh-Hant-CN;q=0.9, ja-CN;q=0.8',
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36',
                    'Referer' => 'https://www.google.com',
                ],
                'timeout' => self::$timeout,
                'http_errors' => false, // http_errors请求参数设置成true，在400级别的错误的时候将会抛出异常
                'cookies' => true,
            ];

            self::$instance = new Client($options);
        }

        return self::$instance;
    }

    /**
     * @param string $name
     *
     * @return array|string
     * @throws \Exception
     */
    public static function cookie($name = '')
    {
        $cookies = self::jar()->toArray();

        if (strlen($name)) {
            foreach ($cookies as $cookie) {
                if ($cookie['Name'] == $name) {
                    Log::debug(sprintf('获取cookie[%s]：%s', $name, $cookie['Value']));

                    return $cookie['Value'];
                }
            }

            return '';
        }

        return $cookies;
    }
}