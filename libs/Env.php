<?php
/**
 * 环境变量
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/6/2
 * @time 17:28
 */

namespace Luolongfei\Lib;

use Dotenv\Dotenv;

class Env
{
    /**
     * @var Env
     */
    protected static $instance;

    /**
     * @var array 环境变量值
     */
    protected static $val;

    public function __construct()
    {
    }

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function load($fileName = '.env')
    {
        if (null === self::$val) {
            self::$val = Dotenv::create(ROOT_PATH, $fileName)->load();
        }

        return self::$val;
    }
}