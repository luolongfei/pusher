<?php
/**
 * 日志
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 12:01
 */

namespace Luolongfei\Lib;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class Log
{
    /**
     * @var Logger
     */
    protected static $instance;

    /**
     * 由于php不能在类外使用已实例化的对象来访问静态属性，但可以在类外访问类里的静态方法，故定义此方法实现类外访问静态属性
     *
     * 注意，info等方法不写日志，error方法才写日志到指定目录
     *
     * @return Logger
     * @throws \Exception
     */
    public static function getLogger()
    {
        if (static::$instance === null) {
            $handler = new StreamHandler(
                config('stdout') ? 'php://stdout' : ROOT_PATH . 'logs/' . date('Y-m-d') . '/push.log',
                config('debug') ? Logger::DEBUG : Logger::INFO
            );
            if (config('stdout')) {
                $handler->setFormatter(new ColoredLineFormatter(null, "[%datetime%] %channel%.%level_name%: %message%\n"));
            }

            $logger = new Logger('pusher');
            $logger->pushHandler($handler);

            self::$instance = $logger;
        }

        return static::$instance;
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function debug($message, array $context = [])
    {
        return self::getLogger()->addDebug($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function info($message, array $context = [])
    {
        return self::getLogger()->addInfo($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function notice($message, array $context = [])
    {
        return self::getLogger()->addNotice($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function warning($message, array $context = [])
    {
        return self::getLogger()->addWarning($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function error($message, array $context = [])
    {
        return self::getLogger()->addError($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function alert($message, array $context = [])
    {
        return self::getLogger()->addAlert($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    public static function emergency($message, array $context = [])
    {
        return self::getLogger()->addEmergency($message, $context);
    }
}