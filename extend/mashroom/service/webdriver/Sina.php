<?php
/**
 * mashroom.service.WebDriverService
 * 
 * @version 1.0.0
 */
namespace mashroom\service\webdriver;

use mashroom\service\AppService;
use mashroom\service\WebDriverService;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;

class Sina extends AppService
{
    /**
     * 连接设备
     *
     * @var object
     */
    private $driver;

    /**
     * 币种汇率信息
     * 
     * @var array
     */
    private $currency;

    /**
     * 应用的接口
     *
     * @var string
     */
    private $channel = 'sina';

    /**
     * 短标题
     *
     * @var array
     */
    private $titles = [
        'HSI' => '恒生指数',
        'HSCEI' => '国企指数',
        'HSCCI' => '红筹指数'
    ];

    /**
     * 接口列表
     *
     * @var array
     */
    protected $paths = [
        'http://finance.sina.com.cn/stock/hkstock/',
        'http://suggest3.sinajs.cn/suggest/type=&key={keyword}&name=suggestdata_1662708571115',
        'http://stock.finance.sina.com.cn/hkstock/quotes/{code}.html'
    ];

    /**
     * 大小写转换
     *
     * @param string $value
     * @return string
     */
    private function convertStringToNumber($value)
    {
        if (strpos($value, '万') !== false) {
            $value = (double)str_replace('万', '', $value);
            $value = $value * 10e3;
        } elseif(strpos($value, '亿') !== false) {
            $value = (double)str_replace('亿', '', $value);
            $value = $value * 10e7;
        }

        return $value;
    }

    public function __construct(WebDriverService $driver)
    {
        parent::__construct();

        $this->driver = $driver;
        $this->currency = redis()->get('te.currency');
    }

    /**
     * 返回币种缓存信息
     *
     * @return void
     */
    public function getForeignExchange()
    {
        return $this->currency;
    }

    /**
     * 模糊搜索返回代码
     *
     * @param string $keyword
     * @return void
     */
    public function getCode($keyword, $retry = false)
    {
        // 访问对象
        $session = $this->driver->handler();

        try {
            // 获取输入框
            $element = $session->findElement(WebDriverBy::id('suggest01_input'));
            $element->click();
            $element->clear();
            $element->sendKeys($keyword);
            // 移动鼠标
            $session->getMouse()->mouseMove($element->getCoordinates());
        } catch(\Exception $e) {
            $this->logger->info('search failed', 'ERROR', [$e->getCode(), $e->getMessage()]);

            if ($e instanceof NoSuchElementException) {
                if ($retry == false) {
                    $this->driver->back();
                    return $this->getCode($keyword, true);
                }
            }

            return false;
        }

        // 获取AJAX内容
        $values = $session->executeScript("function gur(i){sleep(1000);var h=$('#topSearch').find('table').find('tr').eq(1).attr('id');if(h!=undefined||i>5){return h}else{return gur(i+1)}}function sleep(time){return new Promise(function(resolve){setTimeout(resolve,time)})}return gur(0);", [$element]);
        if (strpos($values, ',') === false) {
            return false;
        }
        // 分解异步获取内容
        $values = explode(',', $values);
        $code = $values[2];

        return $code;
    }

    /**
     * 获取大盘信息
     *
     * @return array
     */
    public function getMarket($name = '')
    {
        if ($name == '') {
            return [
                $this->getMarket('hq_str_hkHSI'),
                $this->getMarket('hq_str_hkHSCEI'),
                $this->getMarket('hq_str_hkHSCCI')
            ];
        } else {
            $values = $this->driver->get($name);
            $result = null;

            if ($values != null) {
                $value = explode(',', $values);

                $result = [];
                $result['channel'] = $this->channel;
                $result['code'] = $value[0];
                $result['close'] = $value[3];
                $result['title'] = $value[1];
                $result['shortname'] = $this->titles[$value[0]];
                $result['time'] = array_pop($value);
                $result['date'] = str_replace('/', '-', array_pop($value));
                $result['datetime'] = $result['date'] . ' ' . $result['time'];
                $result['value'] = $value[6];
                $result['amplitude'] = $value[7];
                $result['amplitude_value'] = $value[8];
                $result['original'] = $values;
                $result['timestamp'] = strtotime($result['datetime']);
            }

            return $result;
        }
    }

    /**
     * 返回行情数据
     *
     * @param string $code
     * @return array
     */
    public function getQuotes($code, $opend = true)
    {
        set_time_limit(9999);

        if ($opend) {
            $this->driver->open(function($paths) use($code) {
                return str_replace('{code}', $code, $paths[2]);
            });
        }

        // 访问对象
        $session = $this->driver->handler();

        // validate every 500 milliseconds
        try {
            $wait = 0;
            $session->wait(5, 500)->until(function() use($session, &$wait) {
                $wait ++;
                $element = $session->findElement(WebDriverBy::id('mts_stock_hk_price'));
                if ($element) {
                    return $element->getText() != '--';
                }
            }, '定位等待数据载入错误');
        } catch(\Exception $e) {
            $this->logger->info('instance of failed', 'ERROR', [$e->getCode(), $e->getMessage()]);

            if ($opend) {
                $this->driver->back();
            }

            return null;
        }

        $values = explode(",", $this->driver->get("hq_str_rt_hk{$code}"));

        $data = array();
        $data['channel'] = $this->channel;
        $data['code']  = $code;
        $data['title'] = $values[1];
        $data['open']  = $values[2];            // 今日开盘价
        $data['close'] = $values[3];            // 昨收盘
        $data['high']  = $values[4];            // 最高价
        $data['lowest']= $values[5];            // 最低价
        $data['value'] = $values[6];            // 当前价
        $data['amplitude'] = $values[7];        // 幅值
        $data['ratio'] = $values[8];            // 幅率
        $data['turnover'] = $values[11];        // 成交额(万)
        $data['volume'] = $values[12];          // 成交量(万)
        $data['vibration'] = round(($data['high'] - $data['lowest']) / $data['close'] * 100, 6); // 振幅
        $data['date'] = str_replace('/', '-', $values[17]); // 日期
        $data['time'] = $values[18];            // 时间
        $data['datetime'] = $data['date'] . ' ' . $data['time'];
        $data['timestamp'] = strtotime($data['datetime']);

        $capitals = $this->driver->execute("function getCapital(){var r={};$('.deta_hqContainer>.deta03').find('li').each(function(i,v){var t=v.getElementsByTagName('SPAN')[0],p=t.innerText,s=v.innerHTML.split('<');r[s[0]]=p});return r}return getCapital();");
        if ($capitals) {
            $data['tmc'] = $this->convertStringToNumber($capitals['港股市值']);    // 总市值
            $data['cmv'] = $this->convertStringToNumber($capitals['港股股本']);   // 流通市值
            $data['chands'] = round(($data['volume'] / $data['cmv']) * 100, 6); // 换手 = 成交量÷流通股本×100%
        }

        $this->getCurrency();

        if ($opend) {
            $this->driver->back();
        }

        return $data;
    }

    /**
     * 获取币种汇率信息
     *
     * @return void
     */
    private function getCurrency()
    {
        $values = explode(",", $this->driver->get("hq_str_HKDCNY"));

        $currency = [];
        $currency['channel'] = $this->channel;
        $currency['date'] = array_pop($values); // 日期
        $currency['time'] = $values[0];         // 时间
        $currency['name'] = array_pop($values); // 名称: 港元人民币
        $currency['value'] = array_pop($values); // 当前价
        $currency['lowest'] = array_pop($values);// 最低价
        $currency['high'] = array_pop($values);  // 最高价
        $currency['datetime'] = $currency['date'] . ' ' . $currency['time'];
        $currency['timestamp'] = strtotime($currency['datetime']);

        redis()->set('te.currency', $currency);

        return $currency;
    }
}