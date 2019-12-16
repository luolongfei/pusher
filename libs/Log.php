<?php
/**
 * 日志
 *
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
    protected static $loggerInstance;

    /**
     * @var string 日期目录
     */
    public static $date;

    /**
     * 由于php不能在类外使用已实例化的对象来访问静态属性，但可以在类外访问类里的静态方法，故定义此方法实现类外访问静态属性
     *
     * 注意，info等方法不写日志，error方法才写日志到指定目录
     *
     * @return Logger
     * @throws \Exception
     */
    public static function logger()
    {
        // 日志按每天日期路径归档
        $date = date('Y-m-d');
        if (self::$date !== $date) {
            self::$date = $date;
            self::$loggerInstance = null;
        }

        // 实例化
        if (!self::$loggerInstance instanceof Logger) {
            $handler = new StreamHandler(
                config('stdout') ? 'php://stdout' : sprintf('%s/logs/%s/push.log', ROOT_PATH, $date),
                config('debug') ? Logger::DEBUG : Logger::INFO
            );
            if (config('stdout')) {
                $handler->setFormatter(new ColoredLineFormatter(null, "[%datetime%] %channel%.%level_name%: %message%\n"));
            }

            $logger = new Logger('pusher');
            $logger->pushHandler($handler);

            self::$loggerInstance = $logger;
        }

        return self::$loggerInstance;
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function debug($message, array $context = [])
    {
        return self::logger()->addDebug($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function info($message, array $context = [])
    {
        return self::logger()->addInfo($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function notice($message, array $context = [])
    {
        return self::logger()->addNotice($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function warning($message, array $context = [])
    {
        return self::logger()->addWarning($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function error($message, array $context = [])
    {
        return self::logger()->addError($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function alert($message, array $context = [])
    {
        return self::logger()->addAlert($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     *
     * @return bool
     * @throws \Exception
     */
    public static function emergency($message, array $context = [])
    {
        return self::logger()->addEmergency($message, $context);
    }
}