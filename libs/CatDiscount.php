<?php
/**
 * 喵喵折
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/6/22
 * @time 9:17
 */

namespace Luolongfei\Lib;

use Luolongfei\Lib\Curl;
use Luolongfei\Lib\Log;
use Luolongfei\Lib\Mail;

class CatDiscount
{
    /**
     * 喵喵折基础URL
     */
    const APP_API_URL = 'https://www.henzanapp.com/api/v2/';

    /**
     * 获取商品标准地址
     */
    const GET_STANDARD_URL = 'mmztemplate/getStandardUrl';

    /**
     * 获取商品实时详情
     */
    const GET_GOODS_DETAIL_URL = 'mmzgoods/goodsDetail';

    /**
     * 获取商品历史价格
     */
    const GET_HISTORICAL_PRICE_URL = 'mmzgoods/bottomAreaInfo2';

    /**
     * 模拟喵喵折APP
     */
    const MMZ_IOS_APP = [
        'base_uri' => self::APP_API_URL,
        'headers' => [
            'User-Agent' => 'MiaoMiaoZheApp/1.5.7 (com.miaomiaozheapp.henzan; build:1.5.7; iOS 12.3.1) Alamofire/1.5.7',
            'App-From' => 'ios',
            'Mmz-Ios-Version' => '1.5.6',
            'Php-Auth-Pw' => 'b29b3e141b99e40e2e3153e1a5a2721d',
            'Php-Auth-User' => 'mmzapp_ios',
            'Php-Ios-Client-Id' => 'ab97e6d1616f45249e9c62b2a88708ba',
            'Shu-Meng-Did' => 'D2fqNDdt6VmSRjcLoKZSl3pjKOOGD0JVEaaeRqzAZLg8YXe1',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
        ]
    ];

    /**
     * @var array 合并必要的各个接口的返回值，用于后期组装文言
     */
    protected static $rtData = [];

    /**
     * @var string 当获取价格的某个流程出错，自定义返回文言
     */
    protected static $rtErrorMsg = '';

    /**
     * @var CatDiscount
     */
    protected static $instance;

    public function __construct()
    {
    }

    /**
     * 获取价格走势文言
     *
     * @param string $origStr
     *
     * @return string
     * @throws \Exception
     */
    public static function getPriceText($origStr = '')
    {
        $rt = self::getPrice($origStr);
        if ($rt === false || empty($rt['info'])) {
            return self::$rtErrorMsg ?: '[皱眉]查询价格的某个流程出错了。小伙子别慌，我已经在排查问题了。';
        }

        $bd = '';
        $ed = '';
        $priceText = [];
        $startDate = '';
        $lastDate = '';
        $price = 0;
        $startText = '';
        $count = count($rt['info']);
        $sumVal = 0;
        foreach ($rt['info'] as $key => $item) {
            $dt = $item['dt'];
            $pr = $item['pr'];
            $sumVal += $pr;

            // 第一次进入时，只赋值
            if ($key === 0) {
                $startDate = $dt;
                $lastDate = $dt;
                $price = $pr;
                $bd = $dt;
                $ed = $dt; // 仅有一条数据时赋予正确的结束日期
                continue;
            }

            // 价格变化，定位上个区间结束日期
            if ($pr > $price || $pr < $price) {
                $priceText[] = self::assembly($startText, $startDate, $lastDate, $price);
                $startText = $pr > $price ? '升' : '降';
                $startDate = $dt;
                $price = $pr;
            }

            // 记录上一次日期
            $lastDate = $dt;

            // 单独处理最后一笔数据
            if ($key === ($count - 1)) {
                $priceText[] = self::assembly($startText, $startDate, $lastDate, $price);
                $ed = $dt;
            }
        }
        $avg = $count ? bcdiv($sumVal, $count, 2) : '未知';

        /**
         * 当前走势
         */
        switch ($rt['trend']) {
            case 1:
                $trend = '历史低价';
                break;
            case 2:
                $trend = '价格下降';
                break;
            case 3:
                $trend = '价格上涨';
                break;
            case 4:
            default:
                $trend = '价格平稳';
        }

        $priceText = implode("\n", $priceText) ?: '[皱眉]我也不晓得价格走势，今天才收录，快走，再问打死你。';
        $goodsDetail = self::$rtData['goodsDetail'];
        $text = sprintf(
            "[嘿哈]报告，商品「%s」过去半年（%s - %s）的价格走势如下\n%s\n历史最高价：%s元\n历史最低价：%s元\n过去半年平均价：%s元\n当前：%s\n该商品自上架以来共卖出%d件，分类于「%s」之下，店铺名为「%s」。\n画了个折线图说明一切，点击查看：xxx\n以上。",
            $goodsDetail['title'],
            $bd,
            $ed,
            $priceText,
            $rt['hpr'],
            $rt['lpr'],
            $avg,
            $trend,
            $goodsDetail['sellCount'],
            $goodsDetail['cateName'],
            $goodsDetail['merchantName']
        );

        return $text;
    }

    /**
     * 获取历史价格
     *
     * @param string $origStr
     *
     * @return bool | array
     * @throws \Exception
     */
    public static function getPrice($origStr = '')
    {
        $standardUrl = self::getStandardUrl($origStr);
        if ($standardUrl === false) {
            return false;
        }

        // 商品已下架或来自亚马逊或当当
        if (!$standardUrl['status']) {
            self::$rtErrorMsg = '[皱眉]出错了，它可能是个不存在的商品，可能是商品已下架。';

            // TODO 支持亚马逊以及当当网查询

            return false;
        }

        $goodsDetail = self::getGoodsDetail($standardUrl['goodsId'], $standardUrl['shop']);
        if ($goodsDetail === false) {
            self::$rtErrorMsg = '[皱眉]获取商品详情出错，具体什么情况，咱也不知道，咱也不敢问。小伙子别慌，我已经在排查问题了。';

            return false;
        }

        $response = Curl::post(
            self::GET_HISTORICAL_PRICE_URL,
            [
                'price' => $goodsDetail['price'],
                'url' => $goodsDetail['url']
            ],
            self::MMZ_IOS_APP
        );
        $response = json_decode($response, true);

        if (!$response || $response['RC'] !== 1) {
            LOG::error('获取商品历史价格时出错', $response);
//            Mail::send('报告，喵喵折获取商品历史价格时出错', '今次取得的响应为<br>' . var_export($response, true));
            self::$rtErrorMsg = '[皱眉]获取商品历史价格出错，具体什么情况，咱也不知道，咱也没敢问。小伙子别慌，我已经在排查问题了。';

            return false;
        }

        return $response['data']['pcinfo'];
    }

    /**
     * 获取商品标准地址
     *
     * @param string $origStr
     *
     * @return array|bool
     * @throws \Exception
     */
    public static function getStandardUrl($origStr = '')
    {
        $response = Curl::post(
            self::GET_STANDARD_URL,
            [
                'query' => $origStr
            ],
            self::MMZ_IOS_APP
        );
        $response = json_decode($response, true);

        if (!$response || $response['RC'] !== 1) {
            LOG::error('获取商品标准地址时出错', $response);
//            Mail::send('报告，喵喵折获取商品标准地址时出错', '今次取得的响应为<br>' . var_export($response, true));

            return false;
        }

        $rt = $response['data']['url_info'];

        return [
            'url' => $rt['url'],
            'goodsId' => $rt['goods_id'],
            'shop' => $rt['shop'],
            'status' => $response['data']['status']
        ];
    }

    /**
     * 获取商品实时详情
     *
     * @param string $goodsId
     * @param string $shop
     *
     * @return array|bool
     * @throws \Exception
     */
    public static function getGoodsDetail($goodsId = '', $shop = '')
    {
        $response = Curl::get(
            self::GET_GOODS_DETAIL_URL,
            [
                'id' => $goodsId,
                'mer_code' => '',
                'sku_id' => '',
                'type' => $shop
            ],
            self::MMZ_IOS_APP
        );
        $response = json_decode($response, true);

        if (!$response || $response['RC'] !== 1) {
            LOG::error('获取商品实时详情时出错', $response);
//            Mail::send('报告，喵喵折获取商品实时详情时出错', '今次取得的响应为<br>' . var_export($response, true));

            return false;
        }

        $rt = $response['data']['goods_info'];
        $goodsDetail = [
            'shopName' => $rt['shop_name'],
            'merchantName' => $rt['merchant_name'],
            'cateName' => $rt['cate_name'],
            'multiPic' => $rt['multi_pic'],
            'price' => $rt['price'],
            'sellCount' => $rt['sell_count'],
            'title' => $rt['title'],
            'url' => $rt['url']
        ];
        self::$rtData['goodsDetail'] = $goodsDetail;

        return $goodsDetail;
    }

    /**
     * 组装每一天价格文言
     *
     * @param string $startText
     * @param string $startDate
     * @param string $lastDate
     * @param integer $price
     *
     * @return string
     */
    public static function assembly($startText, $startDate, $lastDate, $price)
    {
        if ($startDate === $lastDate) {
            return sprintf('[%s] %s：%s元', $startText ?: '始', $startDate, $price);
        }

        return sprintf('[%s] %s - %s：%s元', $startText ?: '始', $startDate, $lastDate, $price);
    }

    public static function shopUrlCheck($origStr = '', $rules = [])
    {
        $rules = $rules ?: config('shopUrlRegex');
        if (empty($rules)) {
            return false;
        }

        $regex = '';
        foreach ($rules as $rule) {
            if (strlen($rule) === 0) {
                continue;
            }
            $regex .= '|' . $rule;
        }
        if ($regex === '') {
            return false;
        }
        $regex = sprintf('/(?:%s)/iu', ltrim($regex, '|'));

        if (preg_match($regex, $origStr, $match)) {
            return $match[0];
        }

        return false;
    }

    protected static function instance()
    {
        if (!self::$instance instanceof CatDiscount) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}