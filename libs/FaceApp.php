<?php
/**
 * face app
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/7/20
 * @time 6:25
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Base;
use Luolongfei\Lib\Curl;
use Luolongfei\Lib\Log;

class FaceApp extends Base
{
    /**
     * 获取FaceApp接口地址
     */
    const FACE_APP_HOSTS = 'https://hosts.faceapp.io:443/v3/hosts.json';

    /**
     * 获取userToken地址
     */
    const AUTH_CREDENTIALS_URL = 'https://api.faceapp.io:443/api/v3.0/auth/user/credentials';

    /**
     * 处理照片地址
     */
    const PHOTOS_URL = 'api/v3.9/photos';

    /**
     * 模拟FaceApp
     */
    public static $FACE_APP_IOS = [
        'base_uri' => '',
        'headers' => [
            'User-Agent' => 'FaceApp/3.4.6 (iPhone 6s Plus; iPhone8,2; iOS Version 12.3.1 (Build 16F203); Scale/3.0)',
            'Content-Type' => 'application/json; charset=utf-8',
            'x-faceapp-applaunched' => '', // 时间戳
            'x-faceapp-applaunched-version' => '3.4.6',
            'x-faceapp-deviceid' => '', // 设备ID
            'x-faceapp-ios' => '12.3.1',
            'x-faceapp-model' => 'iPhone 6s Plus'
        ]
    ];

    /**
     * @var FaceApp
     */
    protected static $instance;

    /**
     * @var string 滤镜
     */
    public $filter = 'smile_2';

    /**
     * @var string 用户令牌
     */
    protected $userToken;

    /**
     * @var integer 令牌生命周期
     */
    protected $userTokenTtl = 0;

    /**
     * FaceApp constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * @throws \Exception
     */
    protected function init()
    {
        // 设置设备ID
        $this->setHeader('x-faceapp-deviceid', $this->genDeviceId());

        // 设置应用启动时间
        $this->setHeader('x-faceapp-applaunched', time());

        // 设置接口地址与端口
        $this->setBaseUri($this->getApiBaseUri());

        // 设置用户令牌
        $this->setHeader('x-faceapp-usertoken', $this->getUserToken());
    }

    /**
     * @param string $header
     * @param string $value
     *
     * @return $this
     */
    public function setHeader($header = '', $value = '')
    {
        self::$FACE_APP_IOS['headers'][$header] = $value;

        return $this;
    }

    /**
     * 生成随机设备ID
     *
     * @return string
     */
    public function genDeviceId()
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            $this->genRandStr(8),
            $this->genRandStr(4),
            $this->genRandStr(4),
            $this->genRandStr(4),
            $this->genRandStr(12)
        );
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getUserToken()
    {
        if ($this->userTokenTtl < (time() - 300) && $this->userToken !== null) { // 最少5分钟有效期的token直接返回
            return $this->userToken;
        }

        $response = Curl::post(
            self::AUTH_CREDENTIALS_URL,
            [],
            self::$FACE_APP_IOS
        );
        $response = json_decode($response, true);

        if (!isset($response['user_token']) || !isset($response['user_token_lifetime'])) {
            throw new \Exception('获取usertoken时出错，今次响应内容为：' . json_encode($response));
        }

        $this->userTokenTtl = time() + $response['user_token_lifetime'];
        $this->userToken = $response['user_token'];

        return $this->userToken;
    }

    /**
     * @param string $baseUri
     *
     * @return $this
     */
    public function setBaseUri($baseUri = '')
    {
        self::$FACE_APP_IOS['base_uri'] = $baseUri;

        return $this;
    }

    /**
     * 获取face app最新接口地址与端口
     *
     * @return string
     * @throws \Exception
     */
    public function getApiBaseUri()
    {
        $response = Curl::get(
            self::FACE_APP_HOSTS,
            [],
            self::$FACE_APP_IOS
        );
        $response = json_decode($response, true);

        if (!isset($response[0]['host']) || !isset($response[0]['port'])) {
            throw new \Exception('获取faceapp接口时出错，今次响应内容为：' . json_encode($response));
        }

        return sprintf('%s:%s', $response[0]['host'], $response[0]['port']);
    }

    /**
     * @return FaceApp
     * @throws \Exception
     */
    public static function instance()
    {
        if (!self::$instance instanceof FaceApp) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 设置滤镜
     *
     * @param string $filter
     *
     * @return $this
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function getFilterImage($imgFile = '')
    {
        if (!file_exists($imgFile)) {
            return false;
        }


    }
}