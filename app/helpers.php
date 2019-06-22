<?php
/**
 * 助手函数
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 16:34
 */

use Luolongfei\Lib\Config;
use Luolongfei\Lib\Log;
use Luolongfei\Lib\Env;

if (!function_exists('config')) {
    /**
     * 获取配置
     *
     * @param string $key 键，支持点式访问
     *
     * @return array|mixed
     */
    function config($key = '')
    {
        $allConfig = Config::instance()::getConfig();

        if (strlen($key)) {
            if (strpos($key, '.')) {
                $keys = explode('.', $key);
                $val = $allConfig;
                foreach ($keys as $k) {
                    if (!isset($val[$k])) {
                        return null; // 任一下标不存在就返回null
                    }

                    $val = $val[$k];
                }

                return $val;
            } else {
                if (isset($allConfig[$key])) {
                    return $allConfig[$key];
                }
            }
        }

        return $allConfig;
    }
}

if (!function_exists('is_repeated')) {
    /**
     * 检查文件是否重复
     *
     * @param string $fileName
     * @param string $path
     *
     * @return bool
     * @throws Exception
     */
    function is_repeated($fileName = '', $path = '')
    {
        try {
            $path = $path ?: APP_PATH . 'prevent_duplication/' . date('Y-m-d') . '/';
            $file = $path . $fileName . '.php';

            if (file_exists($file)) return true;

        } catch (\Exception $e) {
            Log::error(sprintf('检查%s文件是否重复时出错，具体错误为：%s', $file, $e->getMessage()));
        }

        return false;
    }
}

if (!function_exists('create_file')) {
    /**
     * 创建文件
     *
     * @param string $fileName
     * @param string $content
     * @param string $path
     *
     * @return bool
     * @throws Exception
     */
    function create_file($fileName = '', $content = '未指定写入内容', $path = '')
    {
        try {
            $path = $path ?: APP_PATH . 'prevent_duplication/' . date('Y-m-d') . '/';
            $file = $path . $fileName . '.php';

            if (!is_dir($path)) {
                mkdir($path, 0777, true);
                chmod($path, 0777);
            }

            $handle = fopen($file, 'a'); // 文件不存在则自动创建

            if (!filesize($file)) {
                fwrite($handle, "<?php defined('APP_PATH') or die('No direct script access allowed.'); ?>" . PHP_EOL . PHP_EOL);
                chmod($file, 0666);
            }

            fwrite($handle, '[' . date('Y-m-d H:i:s') . ']' . PHP_EOL . (is_string($content) ? $content : var_export($content, true)) . PHP_EOL);

            fclose($handle);
        } catch (\Exception $e) {
            Log::error(sprintf('尝试创建%s文件时出错，具体错误为：%s', $file, $e->getMessage()));

            return false;
        }

        return true;
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量值
     *
     * @param $key
     * @param null $default
     *
     * @return array|bool|false|null|string
     */
    function env($key, $default = null)
    {
        Env::instance()->load();

        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') { // 去除双引号
            return substr($value, 1, -1);
        }

        return $value;
    }
}