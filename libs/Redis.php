<?php
/**
 * Redis
 *
 * 此类的封装基于C:/Program Files/JetBrains/PhpStorm 2019.2.1/plugins/php/lib/php.jar!/stubs/redis/Redis.php
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/10/26
 * @time 20:35
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Base;
use Luolongfei\Lib\Log;
use Predis\Client AS RedisClient;

class Redis extends Base
{
    /**
     * @var Redis
     */
    protected static $instance;

    /**
     * @return Redis|RedisClient
     */
    public static function getInstance()
    {
        if (!self::$instance instanceof RedisClient) {
            self::$instance = new RedisClient([
                'scheme' => 'tcp',
                'host' => config('database.redis.host'),
                'password' => config('database.redis.password'),
                'port' => config('database.redis.port'),
                'database' => config('database.redis.database')
            ]);
        }

        return self::$instance;
    }

    /**
     * 设置一个值，并保留$ttl秒
     *
     * 执行成功将返回true
     *
     * @param string $key
     * @param int $ttl 过$ttl秒后过期
     * @param string $value
     *
     * @return bool
     */
    public static function setex($key, $ttl, $value)
    {
        return self::getInstance()->setex($key, $ttl, $value);
    }

    /**
     * 设定指定键的值
     *
     * 默认不过期
     *
     * @param string $key
     * @param string $value
     * @param int | array $timeout [optional] Calling setex() is preferred if you want a timeout.<br>
     *                      Since 2.6.12 it also supports different flags inside an array. Example ['NX', 'EX' => 60]<br>
     *                      EX seconds -- Set the specified expire time, in seconds.<br>
     *                      PX milliseconds -- Set the specified expire time, in milliseconds.<br>
     *                      PX milliseconds -- Set the specified expire time, in milliseconds.<br>
     *                      NX -- Only set the key if it does not already exist.<br>
     *                      XX -- Only set the key if it already exist.<br>
     *                      不建议用此参数，要设置过期时间建议使用setex()方法
     *
     * @return bool 设置成功返回true，失败返回false
     */
    public static function set($key, $value, $timeout = 0)
    {
        return self::getInstance()->set($key, $value, $timeout = 0);
    }

    /**
     * 设置指定键在指定时间戳过期
     *
     * @param $key
     * @param $timestamp
     *
     * @return bool 成功返回true，失败返回false
     *
     * @example
     * <pre>
     * $redis->expireAt('x', $now + 3); // x will disappear in 3 seconds.
     * sleep(5);                        // wait 5 seconds
     * $redis->get('x');                // will return `FALSE`, as 'x' has expired.
     * </pre>
     */
    public static function expireAt($key, $timestamp)
    {
        return self::getInstance()->expireAt($key, $timestamp);
    }

    /**
     * 获取指定键对应的值
     *
     * @param $key
     *
     * @return string | bool 如果指定键不存在，则返回false
     */
    public static function get($key)
    {
        return self::getInstance()->get($key);
    }

    /**
     * 验证指定的一个或多个键是否存在
     *
     * 此函数接受单个参数，并且在phpredis版本<4.0.0中返回true或false
     * @param string $key
     *
     * @return int
     *
     * @example
     * <pre>
     * $redis->set('key', 'value');
     * $redis->exists('key'); // 1
     * $redis->exists('NonExistingKey'); // 0
     *
     * $redis->mset(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz']);
     * $redis->exists(['foo', 'bar', 'baz]); // 3
     * $redis->exists('foo', 'bar', 'baz'); // 3
     * </pre>
     */
    public static function exists($key)
    {
        return self::getInstance()->exists($key);
    }

    /**
     * 删除指定的键
     *
     * @param $key1
     * @param null $key2
     * @param null $key3
     *
     * @return int 被删除的键的个数
     *
     * @see del() 同del函数，但此函数最多指定三个键
     */
    public static function delete($key1, $key2 = null, $key3 = null)
    {
        return self::getInstance()->delete($key1, $key2 = null, $key3 = null);
    }

    /**
     * 删除指定的键
     *
     * @param string | array $key1 字符串则是指定某个键，数组则指定数组中所有值对应的键
     * @param mixed ...$otherKeys
     *
     * @return int 被删除的键的个数
     *
     * @example
     * <pre>
     * $redis->set('key1', 'val1');
     * $redis->set('key2', 'val2');
     * $redis->set('key3', 'val3');
     * $redis->set('key4', 'val4');
     * $redis->delete('key1', 'key2');          // return 2
     * $redis->delete(array('key3', 'key4'));   // return 2
     * </pre>
     */
    public static function del($key1, ...$otherKeys)
    {
        return self::getInstance()->del($key1, ...$otherKeys);
    }
}