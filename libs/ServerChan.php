<?php
/**
 * Server酱微信推送
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2018/7/25
 * @time 13:40
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Curl;

class ServerChan
{
    /**
     * 微信推送
     * @param string $title
     * @param string $content
     * @param string $sendKey 不同的$sendKey对应不同的人，不同的人对应不同的通道
     * @return mixed
     * @throws \Exception
     */
    public static function send($title = '', $content = '', $sendKey = '')
    {
        if ($sendKey === '') {
            $sendKey = config('sendKey');
        }

        $pushContent = [
            'sendkey' => $sendKey,
            'text' => $title,
            'desp' => str_replace("\n", "\n\n", $content) // Server酱接口限定，两个\n等于一个换行
        ];

        $result = Curl::get(SC_URL, $pushContent);

        $rt = json_decode($result, true);
        if ($rt['code'] !== 0) {
            Log::error(
                'Server酱微信推送接口没有正确响应，本次推送可能不成功',
                array_merge($rt, $pushContent)
            );
        }

        return $rt;
    }
}