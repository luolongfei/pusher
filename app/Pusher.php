<?php
/**
 * 推课
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2019/3/3
 * @time 17:46
 */

namespace Luolongfei\App;

use Luolongfei\Lib\Log;
use Luolongfei\Lib\Curl;
use Luolongfei\Lib\ServerChan;

class Pusher
{
    /**
     * MEET_DATE
     */
    const MEET_DATE = '2018-12-29';

    /**
     * LOVE_DATE_START
     */
    const LOVE_DATE_START = '2019-03-31';

    /**
     * @var Pusher
     */
    protected static $instance;

    /**
     * @return Pusher
     */
    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function getWeek()
    {
        $week = ['天', '一', '二', '三', '四', '五', '六']; // 0（表示星期天）到 6（表示星期六）

        return '星期' . $week[date('w')];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function handle()
    {
        $startHandleTime = time();

        while (true) {
            if ((time() - $startHandleTime) > 1800) { // 每次循环半小时，挂起后由Supervisor重新拉起
                break;
            }

            foreach (config('classes.' . date('w')) as $timeRange => $class) { // 只遍历当天的课程
                $date = date('Y-m-d');
                list($start, $end) = explode('-', $timeRange);

                $fileName = str_replace(':', '_', $start);
                if (is_repeated($fileName)) {
                    usleep(500000);
                    continue;
                }

                $startTime = strtotime($date . ' ' . $start);
                $endTime = strtotime($date . ' ' . $end);
                $now = time();

                if ($now <= $startTime && ($startTime - $now) <= config('inAdvance') && $class) { // 提前几分钟推送
                    try {
                        // 随机诗词
                        $poetryApi = [
                            'shuqing/aiqing',
                            /*'shuqing/sinian',
                            'shuqing/gudu',
                            'renwu/nvzi',
                            'rensheng/lizhi',
                            'rensheng/qingchun',*/
                        ];
                        $poetry = Curl::get(sprintf('https://api.gushi.ci/%s.json', $poetryApi[mt_rand(0, count($poetryApi) - 1)]));
                        $poetry = json_decode($poetry, true);

                        $poetrySummary = '';
                        $poetryContent = isset($poetry['content']) ? $poetry['content'] : '';
                        if (!$poetryContent) {
                            throw new \Exception('诗词接口返回的数据异常');
                        }

                        $poetrySummary = sprintf(
                            "诗词取自%s写的《%s》, 分类于「%s」之下。\n\n%s\n\n报告完毕。肖可爱开始上课吧啦啦啦~",
                            $poetry['author'],
                            $poetry['origin'],
                            $poetry['category'],
                            $poetryContent
                        );
                    } catch (\Exception $e) {
                        Log::error('获取随机诗词出错：' . $e->getMessage());
                    }

                    list($minute, $second) = explode('.', bcdiv($startTime - time(), 60, 2));
                    $second = bcmul('0.' . $second, 60);

                    $title = sprintf(
                        '肖可爱，该上「%s」课啦，距上课还有%s分%s秒。',
                        $class,
                        $minute < 0 ? 0 : $minute,
                        $second < 10 ? '0' . $second : $second
                    );
                    $content = sprintf(
                        "今天是师父和我屋肖可爱相识的第%s天，正式相爱的第%s天，第%s个小时。你屋师父正在\n想你~\n%s\nby 爱肖可爱的师父",
                        self::LOVE(self::MEET_DATE),
                        self::LOVE(),
                        self::LOVE(self::LOVE_DATE_START, 'h'),
                        $poetrySummary
                    );

                    $rt = ServerChan::send($title, $content);
                    create_file($fileName, $rt);
                }
            }

            usleep(500000); // 防止执行过快，内存占用过高
        }

        return true;
    }

    /**
     * @param string $date
     * @param string $timeType h:hour|d:day
     * @return float|string
     */
    public static function LOVE($date = '', $timeType = 'd')
    {
        $date = $date ?: self::LOVE_DATE_START;
        $start = strtotime($date);

        $loveDayNum = '无穷大';
        switch ($timeType) {
            case 'h':
                $loveDayNum = ceil((time() - $start) / 3600);
                break;
            case 'd':
                $loveDayNum = ceil((time() - $start) / (24 * 3600));
                break;
        }

        return $loveDayNum;
    }
}