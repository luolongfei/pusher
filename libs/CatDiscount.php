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
     * 获取商品最近半年历史价格
     */
    const GET_HISTORICAL_PRICE_URL = 'mmzgoods/bottomAreaInfo2';

    /**
     * 获取商品最近一年历史价格
     */
    const GET_ALL_HISTORICAL_PRICE_URL = 'mmzgoods/allPriceCurve';

    /**
     * 模拟喵喵折APP
     */
    const MMZ_IOS_APP = [
        'base_uri' => self::APP_API_URL,
        'headers' => [
            'User-Agent' => 'MiaoMiaoZheApp/1.6.4 (com.miaomiaozheapp.henzan; build:1.6.4; iOS 13.1.3) Alamofire/1.6.4',
            'App-From' => 'ios',
            'Mmz-Ios-Version' => '1.6.4',
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
     * @var array 商品所有数据，缓存到redis后期会用到
     */
    public static $allData = [];

    /**
     * @var bool 整个处理流程是否完全完成
     */
    public static $success = false;

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
        // 还原完成标记
        self::$success = false;

        $rt = self::getPrice($origStr);
        if ($rt === false || empty($rt['info'])) {
            return self::$rtErrorMsg ?: '[皱眉][皱眉]查询价格的某个流程出错了。小伙子别慌，我已经在排查问题了。';
        }

        $allPrice = $rt['info'];
        self::$allData['allPrice'] = $allPrice; // 缓存所有价格，用于画图
        $count = 0;
        $sumVal = 0;
        $hpr = 0;
        $hprDt = '';
        $lpr = 0;
        $lprDt = '';
        $changeNum = 0;
        $lastPr = 0;
        $lastYear11 = date('Y/m/d', strtotime(sprintf('%s %s', date('Y-11-11'), '-1 year'))); // 去年双十一
        $lastYear11Price = 0;
        foreach ($allPrice as $item) {
            $dt = $item['dt'];
            $pr = $item['pr'];

            // 去年双十一价格
            if ($dt === $lastYear11) {
                $lastYear11Price = $pr;
            }

            // 记录价格变动次数
            if ($lastPr === 0) {
                $lastPr = $pr;
            } else if ($pr !== $lastPr) {
                $lastPr = $pr;
                $changeNum++;
            }

            // 最高价及出现日期
            if ($pr >= $hpr) {
                $hpr = $pr;
                $hprDt = $dt;
            }

            // 最低价及出现日期
            if ($lpr === 0 || $pr <= $lpr) {
                $lpr = $pr;
                $lprDt = $dt;
            }

            $sumVal += $pr;
            $count++;
        }

        if ($count < 2) {
            return '[囧][囧]我也不晓得这玩意儿的历史价格，没收录，刚看到，已经拿小本本记下来了。';
        }
        $avg = bcdiv($sumVal, $count, 2);
        $startDate = $allPrice[0]['dt'];
        $endDate = $allPrice[$count - 1]['dt'];
        $currPr = $allPrice[$count - 1]['pr'];
        $monthText = self::getIntervalMonthText($startDate, $endDate);

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

        $goodsDetail = self::$rtData['goodsDetail'];

        $text = sprintf(
            "商品「%s」过去%s的价格情况如下\n\n%s\n共卖出：%s\n价格共变动：%d次\n当前：%s元\n最近：%s\n\n以上",
            $goodsDetail['title'],
            $monthText,
            $changeNum === 0 ? sprintf(
                '历史价格暂无变动，一直是%d元',
                $currPr
            ) : sprintf(
                "历史最高价：%s元（%s）\n历史最低价：%s元(%s)\n历史平均价：%s元%s",
                $hpr,
                $hprDt,
                $lpr,
                $lprDt,
                $avg,
                $lastYear11Price ? sprintf("\n去年双十一：%s元（%s）", $lastYear11Price, $lastYear11) : ''
            ),
            $goodsDetail['sellCount'] ? sprintf('%s件', $goodsDetail['sellCount']) : '未知或无人购买',
            $changeNum,
            $currPr,
            $trend
        );

        // 标记本次处理已全部完成
        self::$success = true;

        return $text;
    }

    /**
     * 获取历史价格
     *
     * @param string $origStr
     *
     * @return bool|array
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
            self::$rtErrorMsg = '[皱眉][皱眉]出错了，可能是商品已下架或者是暂不支持查询的电商。';

            // TODO 支持亚马逊以及当当网查询

            return false;
        }

        $goodsDetail = self::getGoodsDetail($standardUrl['goodsId'], $standardUrl['shop']);
        if ($goodsDetail === false) {
            self::$rtErrorMsg = '[皱眉][皱眉]查不了，可能是因为商品已下架。';

            return false;
        }

        $response = Curl::post(
            self::GET_ALL_HISTORICAL_PRICE_URL, // 直接获取最近一年价格，不再调用最近半年价格接口
            [
                'zan_goods_id' => $goodsDetail['zan_goods_id'], // 若调最近半年价格接口，京东此参数应对应jd_zan_goods_id
                'price' => $goodsDetail['price'],
                'url' => $goodsDetail['url']
            ],
            self::MMZ_IOS_APP
        );
        $response = json_decode($response, true);

        // 记录商品标题
        self::$allData['title'] = $goodsDetail['title'];

        if (!$response || $response['RC'] !== 1) {
            LOG::error('获取商品历史价格时出错', $response);
//            Mail::send('报告，喵喵折获取商品历史价格时出错', '今次取得的响应为<br>' . var_export($response, true));
            self::$rtErrorMsg = '[皱眉][皱眉]获取商品历史价格出错，具体什么情况，咱也不知道，咱也没敢问。小伙子别慌，我已经在排查问题了。';

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
                /*'mer_code' => '',
                'sku_id' => '',*/
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
            'url' => $rt['url'],
            'zan_goods_id' => $goodsId,
            'jd_zan_goods_id' => $response['data']['zan_goods']['id'] ?? null
        ];
        self::$rtData['goodsDetail'] = $goodsDetail;

        return $goodsDetail;
    }

    public static function getIntervalMonthText($startDate, $endDate)
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        $monthNum = (date('Y', $end) - date('Y', $start)) * 12 + (date('n', $end) - date('n', $start));
        if ($monthNum === 6) {
            $text = '半年';
        } else if ($monthNum >= 11) { // 允许不到或超过12个月
            $text = '一年';
        } else if ($monthNum === 0) {
            $text = sprintf('%d天', floor(($end - $start) / 86400));
        } else {
            $text = sprintf('%d个月', $monthNum);
        }

        return sprintf(
            '%s（%s至%s）',
            $text,
            date('Y-m-d', $start),
            date('Y-m-d', $end)
        );
    }

    /**
     * 价格数组转文言
     * 将每一天的价格数据转为文言，多天同一价格组为一句文言，并在每条文言前标注价格升降情况
     *
     * @param array $allPrice
     *
     * @return string
     */
    public static function allPriceToText(array $allPrice)
    {
        $priceText = [];
        $startDate = '';
        $lastDate = '';
        $price = 0;
        $startText = '';
        $count = count($allPrice);
        foreach ($allPrice as $key => $item) {
            $dt = $item['dt'];
            $pr = $item['pr'];

            // 第一次进入时，只赋值
            if ($key === 0) {
                $startDate = $dt;
                $lastDate = $dt;
                $price = $pr;
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
            }
        }

        return implode("\n", $priceText);
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

    public static function goodsUrlCheck($origStr = '', $rules = [])
    {
        $rules = $rules ?: config('goodsUrlRegex');
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