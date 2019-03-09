<?php
/**
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/2
 * @time 11:05
 */

error_reporting(E_ERROR);
ini_set('display_errors', 1);
set_time_limit(0);

define('IS_CLI', PHP_SAPI === 'cli' ? true : false);
define('DS', DIRECTORY_SEPARATOR);
define('VENDOR_PATH', realpath('vendor') . DS);
define('APP_PATH', realpath('app') . DS);
define('ROOT_PATH', realpath(APP_PATH . '..') . DS);

date_default_timezone_set('Asia/Shanghai');

// Server酱微信推送url
define('SC_URL', 'https://pushbear.ftqq.com/sub');

/**
 * 注册错误处理
 */
register_shutdown_function('customize_error_handler');

/**
 * 注册异常处理
 */
set_exception_handler('exception_handler');

require VENDOR_PATH . 'autoload.php';

use Luolongfei\Lib\Log;
use Luolongfei\App\Pusher;

/**
 * @throws Exception
 */
function customize_error_handler()
{
    if (!is_null($error = error_get_last())) {
        Log::error('程序意外终止', $error);
    }
}

/**
 * @param $e
 * @throws Exception
 */
function exception_handler(Exception $e)
{
    Log::error('未捕获的异常: ' . $e->getMessage());
}

Pusher::instance()->handle();