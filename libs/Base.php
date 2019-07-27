<?php
/**
 * 基础类库
 * 定义公共方法
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/7/20
 * @time 11:02
 */

namespace Luolongfei\Lib;

class Base
{
    /**
     * 生成一个随机字符串
     *
     * @param int $length 字符串长度
     * @param int $type 类型 0：大写字母+数字 1：小写字母+数字 2：纯数字 3：纯大写字母 4：纯小写字母 5：纯大小写字母 6：数字+大小写字母
     *
     * @return string
     */
    public function genRandStr($length = 10, $type = 0)
    {
        switch ($type) {
            case 0:
                $char = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 1:
                $char = '0123456789abcdefghijklmnopqrstuvwxyz';
                break;
            case 2:
                $char = '0123456789';
                break;
            case 3:
                $char = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 4:
                $char = 'abcdefghijklmnopqrstuvwxyz';
                break;
            case 5:
                $char = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 6:
            default:
                $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $randStr = '';
        for ($i = 0; $i < $length; $i++) {
            $randStr .= $char[mt_rand(0, strlen($char) - 1)];
        }
        
        return $randStr;
    }
}