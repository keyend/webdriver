<?php
/**
 * mashroom.service.WebDriverService
 * 
 * @version 1.0.0
 */
namespace mashroom\service\webdriver;

use mashroom\service\AppService;
use mashroom\service\WebDriverService;
use mashroom\exception\HttpException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;

class Hkex extends AppService
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
    private $channel = 'Hkex';

    /**
     * 短标题
     *
     * @var array
     */
    private $codes = [
        '恒生指数' => 'HSI',
        '国企指数' => 'HSCEI',
        '红筹指数' => 'HSCCI'
    ];

    /**
     * 短标题
     *
     * @var array
     */
    private $titles = [
        'HSI' => '恒生指数',
        'HSCE' => '国企指数',
        'HSTECH' => '红筹指数'
    ];

    /**
     * 接口列表
     *
     * @var array
     */
    protected $paths = [
        'https://sc.hkex.com.hk/TuniS/www.hkex.com.hk/?sc_lang=zh-cn',
        'https://sc.hkex.com.hk/TuniS/www.hkex.com.hk/Market-Data/Securities-Prices/Equities/Equities-Quote?sym={code}&sc_lang=zh-cn',
        'https://www.msn.cn/zh-cn/money/currencyconverter?duration=1D'
    ];

    public function __construct(WebDriverService $driver)
    {
        parent::__construct();

        $this->driver = $driver;
        $this->currency = redis()->get('te.currency');
        $this->market = redis()->get('te.market');
    }

    /**
     * 格式化
     *
     * @param string $code
     * @return string
     */
    private function format_code($code)
    {
        if (strlen($code) > 5) {
            return $code;
        } else {
            return substr('0000' . $code, -5);
        }
    }

    /**
     * 前置操作
     *
     * @return string
     */
    public function before()
    {
        // 去除不需要的
        $this->driver->execute("\$('#CookieForm').remove();");
        // 大盘指数
        $markets = $this->market;
        if (empty($markets) || $markets[0]['channel'] != $this->channel || TIMESTAMP - $markets[0]['timestamp'] > 600) {
            $this->getLatestMarket();
        }
        // 每10分钟更新一次汇率
        $currency = $this->currency;
        if (empty($currency) || !is_array($currency) || $currency['channel'] != $this->channel || TIMESTAMP - $currency['timestamp'] > 600) {
            $this->getCurrency();
        }
    }

    /**
     * 获取大盘信息
     *
     * @return array
     */
    public function getMarket()
    {
        $markets = $this->market;
        if ($markets != null || $markets[0]['channel'] != $this->channel || TIMESTAMP - $markets[0]['timestamp'] > 600) {
            $markets = $this->getLatestMarket();
        }

        return $markets;
    }

    /**
     * 获取大盘信息
     *
     * @param string $value
     * @return array
     */
    private function getLatestMarket($value = '')
    {
        if ($value === '') {
            $values = $this->driver->execute("function g(){var d=[];\$('.market_marquee .marqueeInfo').each(function(a,b){b=\$(b);a={};var c=b.find('.col_change').html().replace(/<[^>]+>/g,'').split(' ');a.code=b.attr('ric').substr(1);a.name=b.find('.col_name').text();a.value=b.find('.col_last').text();a.amplitude=c[0];a.variation=c[1].substr(1,c[1].length-2);d.push(a)});return d}return g();");
            $result = [];

            foreach($values as $value) {
                $result[] = $this->getLatestMarket($value);
            }

            $this->market = $result;
            redis()->set('te.market', $result);
    
            return $result;
        } else {
            $result = [];
            $result['channel'] = $this->channel;
            $result['code'] = $value['code'];
            $result['title'] = $value['name'];
            $result['shortname'] = isset($this->titles[$value['code']]) ? $this->titles[$value['code']] : $value['name'];
            $result['value'] = str_replace('+', '', $value['value']);
            $result['amplitude'] = $value['amplitude'];
            $result['variation'] = str_replace('%', '', $value['variation']);
            $result['timestamp'] = TIMESTAMP;

            return $result;
        }
    }

    /**
     * 返回币种缓存信息
     *
     * @return void
     */
    public function getForeignExchange($value = '')
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
        // 去除不需要的
        $session->executeScript("\$('.hkexweb-hero-banner__bottom').parent().remove();\$('#CookieForm').remove();");

        try {
            // 获取容器
            $wrapper = $session->findElement(WebDriverBy::id('lhkexw-hpwidget'));
            $session->getMouse()->mouseMove($wrapper->getCoordinates());
            $wrapper->click();

            // 获取输入框
            $element = $wrapper->findElement(WebDriverBy::id('tags'));
            $element->click();
            $element->clear();
            $element->sendKeys($keyword);
            // 移动鼠标
            $session->getMouse()->mouseMove($element->getCoordinates());
        } catch(\Exception $e) {
            $this->logger->info('Search FAIL', 'Error', [$e->getCode(), $e->getMessage()]);

            if ($e instanceof NoSuchElementException) {
                if ($retry == false) {
                    $this->driver->back();
                    return $this->getCode($keyword, true);
                }
            }

            throw new HttpException($e->getMessage());
        }

        try {
            $session->wait(5, 500)->until(function() use($session, &$wait) {
                $element = $session->findElement(WebDriverBy::cssSelector('.searchbox .result tr'));
                if ($element) {
                    return true;
                }
            }, '读取失败!');
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        // 获取AJAX内容
        try {
            $values = $session->executeScript("function g(){var b={};\$('.searchbox .result tr').each(function(c,a){a=\$(a).find('td');b.code=a.eq(0).text();b.name=a.eq(1).text();b.type=a.eq(2).text()});return b}return g();", [$element]);
            if ($values == null) {
                return false;
            }
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        // 分解异步获取内容
        $code = $this->format_code($values['code']);

        return $code;
    }

    /**
     * 英制单位与数字单位与转
     *
     * @param string $value
     * @return string
     */
    private function convertStringToNumber($value)
    {
        $value = str_replace($value, 'M', 10e5);
        $value = str_replace($value, 'B', 10e8);
        $value = str_replace($value, 'K', 10e2);
        $value = str_replace($value, 'T', 10e2);
        $value = str_replace($value, 'W', 10e3);

        return $value;
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

        $code = ltrim($code, '0');

        if ($opend) {
            $this->driver->open(function($paths) use($code) {
                return str_replace('{code}', $code, $paths[1]);
            });
        }

        // 访问对象
        $session = $this->driver->handler();

        try {
            $session->wait(10, 500)->until(function() use($session, &$wait) {
                $element = $session->findElement(WebDriverBy::cssSelector('.section_table .left_list_title .col_name'));
                if ($element) {
                    if ($element->getText() != '') {
                        return true;
                    }
                }
            }, '读取失败!');
        } catch(\Exception $e) {
            $this->driver->back();
            throw new HttpException($e->getMessage());
        }

        // 获取结果
        try {
            $values = $session->executeScript("function g(){var a=[],b={},e={},f={},g={},d=\$('#lhkexw-quoteequities'),t=d.find('.left_list_title .col_name').text().split(' '),p=function(v){v=v.replace(' HKT',''),v=v.replace('年','-'),v=v.replace('月','-'),v=v.replace('日',' '),v+=':'+(new Date()).getSeconds();return v};b.name='\u5f53\u524d';b.value=d.find('.left_list_title p').eq(1).find('.col_last').text();b.amplitude=d.find('.left_list_title p').eq(2).find('span').eq(0).text();b.variation=d.find('.left_list_title p').eq(2).find('span').eq(1).text().replace(/[^\d\.]+/g,'');a.push(b);e.name='\u6807\u9898';e.value=t[0];a.push(e);f.name='\u4ee3\u7801';f.value=t[1].replace(/[\(\)]/g,'');a.push(f);g.name='\u65f6\u95f4';g.value=p(d.find('.lastupdated span').eq(1).text()),a.push(g),d.find('.left_list_leve tr').each(function(i,v){var td=\$(v).find('td');if(td.eq(0).text()!=''){a.push({name:td.eq(0).text(),value:td.eq(1).text().replace('HK$','')})}}),d.find('.left_list_item dl').each(function(i,v){\$(v).find('abbr').remove();var td=\$(v).children();a.push({name:td.eq(0).text(),value:td.eq(1).text().replace('HK$','')})});return a}return g();");
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        if ($values == null) {
            return false;
        }

        $data = array();
        $data['channel'] = $this->channel;
        $data['code']  = $code;
        $data['close'] = 0;
        $data['high'] = 0;
        $data['lowest'] = 0;
        $data['tmc'] = 0;
        $data['cmv'] = 0;
        $data['volume'] = 0;
        $mapper = [
            '标题' => 'title',
            '上日收市' => 'close',      // 昨收盘,
            '开市' => 'open',           //今开盘
            '最高价' => 'high',         // 最高价
            '最低价' => 'lowest',       // 最低价
            '当前' => 'value',          // 当前价
            '成交金额' => 'turnover',   // 成交额(万)
            '成交数量' => 'volume',     // 成交量(万)
            '市值' => 'tmc',            // 总市值
            '市值' => 'cmv',            // 流通市值
        ];

        foreach($values as $value) {
            if($value['name'] == '时间') {
                $data['datetime'] = $value['value'];
                $data['timestamp'] = strtotime($data['datetime']);
            } elseif($value['name'] == '当前') {
                $data['value'] = $value['value'];// 当前价
                $data['amplitude'] = $value['amplitude'];// 幅值
                $data['ratio'] = $value['variation'];// 幅率
            } elseif($value['name'] == '市值') {
                $data['tmc'] = $value['value'];
                $data['cmv'] = $value['value'];
            } elseif(isset($mapper[$value['name']])) {
                $data[$mapper[$value['name']]] = $value['value'];
            }
        }

        $data['close'] = floatval($data['close']);
		$data['high'] = floatval($data['high']);
		$data['lowest'] = floatval($data['lowest']);
        $data['vibration'] = '0';
        $data['chands'] = 0;

        if ($data['close'] != 0) {
			$deffince = $data['high'] - $data['lowest'];
			if ($deffince != 0) {
            	$data['vibration'] = round($deffince / $data['close'] * 100, 6); // 振幅
			}
        }

        $data['tmc'] = $this->convertStringToNumber($data['tmc']);    // 总市值
        $data['cmv'] = $this->convertStringToNumber($data['cmv']);   // 流通市值
        $data['turnover'] = $this->convertStringToNumber($data['turnover']);   // 流通市值
        $data['cmv'] = (float)$data['cmv'];
        $data['volume'] = (float)$data['volume'];

        if ($data['cmv'] != 0 && $data['volume'] != 0) {
            $data['chands'] = round(($data['volume'] / $data['cmv']) * 100, 6); // 换手 = 成交量÷流通股本×100%
        }

        $currency = $this->currency;

        if ($opend) {
            $this->driver->back();
        }

        return compact('data', 'currency');
    }

    /**
     * 获取币种汇率信息
     *
     * @return void
     */
    public function getCurrency()
    {
        $this->driver->open($this->driver->routes(2));
        // 访问对象
        $session = $this->driver->handler();

        try {
            // 获取容器
            $wrapper = $session->findElement(WebDriverBy::id('onetrust-accept-btn-handler'));
            $session->getMouse()->mouseMove($wrapper->getCoordinates());
            // 同意接受条款
            $wrapper->click();
        } catch(\Exception $e) {
            // 如果已存在COOKIE记录
        }

        try {
            $session->wait(5, 500)->until(function() use($session, &$wait) {
                $element = $session->findElement(WebDriverBy::className('listWrapper-DS-EntryPoint1-2'));
                if ($element) {
                    return true;
                }
            }, '读取失败!');
        } catch(\Exception $e) {
            $this->driver->back();
            throw new HttpException($e->getMessage());
        }

        $currency = null;

        try {
            $values = $this->driver->execute("function g(){var a=[];d=document.querySelector('.listWrapper-DS-EntryPoint1-2').childNodes;d.forEach(v=>{a.push({title:v.childNodes[1].title,name:v.childNodes[1].childNodes[0].innerText,value:v.childNodes[3].childNodes[0].title,amplitude:v.childNodes[3].childNodes[1].innerText.replace('%','').substr(1)})});return a}return g();");
    
            if (is_array($values)) {
                foreach($values as $value) {
                    if ($value['name'] == 'HKD/CNY') {
                        $currency = $value;
                        $currency['channel'] = $this->channel;
                        $currency['timestamp'] = TIMESTAMP;
                        $currency['amplitude'] = trim($currency['amplitude']);
                        $currency['datetime'] = date('Y-m-d H:i:s', TIMESTAMP);
                        $this->currency = $currency;
                        redis()->set('te.currency', $currency);
                    }
                }
            }
        } catch(\Exception $e) {
            // 获取失败
        }

        $this->driver->back();

        return $currency;
    }
}
