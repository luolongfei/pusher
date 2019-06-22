<?php
/**
 * 配置
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 16:41
 */

namespace Luolongfei\Lib;

class Config
{
    /**
     * @var Config
     */
    protected static $instance;

    /**
     * @var array 配置
     */
    protected static $config;

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

    /**
     * @return array|mixed
     */
    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = require ROOT_PATH . 'config.php';
        }

        return self::$config;
    }
}