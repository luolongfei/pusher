<?php
/**
 * curl
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 12:41
 */

namespace Luolongfei\Lib;

use GuzzleHttp\Client;
use Luolongfei\Lib\Log;

class Curl
{
    protected static $instance;
    protected static $jar;

    public static function getJar()
    {
        if (!self::$jar) {
            self::$jar = new \GuzzleHttp\Cookie\CookieJar;
        }

        return self::$jar;
    }

    /**
     * @return Client
     */
    public static function getClient()
    {
        if (self::$instance === null) {
            $options = [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Encoding' => 'gzip',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,ja;q=0.8,en;q=0.7',
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36',
                    'Referer' => 'https://www.google.com',
                ],
                'timeout' => 20.0,
                'http_errors' => false,
                'cookies' => true,
            ];

            self::$instance = new \GuzzleHttp\Client($options);
        }

        return self::$instance;
    }

    /**
     * @param $url
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public static function get($url, $params = [])
    {
        $payload = [
            'cookies' => self::getJar(),
        ];
        if ($params) {
            $payload['query'] = $params;
        }

        Log::info('GET请求: ' . $url, $params);
        $request = self::getClient()->get($url, $payload);
        $body = (string)$request->getBody();
        Log::notice('返回结果为: ' . $body);

        return $body;
    }

    /**
     * @param $url
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public static function post($url, $params = [])
    {
        $payload = [
            'cookies' => self::getJar(),
        ];
        if ($params) {
            $payload['form_params'] = $params;
        }

        Log::info('POST请求: ' . $url, $params);
        $request = self::getClient()->post($url, $payload);
        $body = (string)$request->getBody();
        Log::notice('返回结果为: ' . $body);

        return $body;
    }

    /**
     * @param string $name
     * @return array|string
     * @throws \Exception
     */
    public static function cookie($name = '')
    {
        $cookies = self::getJar()->toArray();

        if (strlen($name)) {
            foreach ($cookies as $cookie) {
                if ($cookie['Name'] == $name) {
                    Log::info('获取 cookie[' . $name . ']: ' . $cookie['Value']);
                    return $cookie['Value'];
                }
            }

            return '';
        }

        return $cookies;
    }
}