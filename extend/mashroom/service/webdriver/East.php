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

class East extends AppService
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
    private $channel = 'east';


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
     * 接口列表
     *
     * @var array
     */
    protected $paths = [
        'https://hk.eastmoney.com/',
        'http://quote.eastmoney.com/hk/{code}.html',
        'http://quote.eastmoney.com/forex/HKDCNYI.html'
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
        $this->market = redis()->get('te.market');
    }

    /**
     * 前置操作
     *
     * @return string
     */
    public function before()
    {
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
            $values = $this->driver->execute("function gur(){var c=[];\$('#xiangGang .list li').each(function(a,b){a={};b=\$(b).children('span');a.name=b[0].innerText;a.value=b[1].innerText;a.amplitude=b[2].innerText;a.variation=b[3].innerText;c.push(a)});return c}return gur();");
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
            $result['code'] = $this->codes[$value['name']];
            $result['title'] = $value['name'];
            $result['shorttitle'] = $value['name'];
            $result['value'] = $value['value'];
            $result['amplitude'] = $value['amplitude'];
            $result['variation'] = $value['variation'];
            $result['timestamp'] = TIMESTAMP;

            return $result;
        }
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
            $element = $session->findElement(WebDriverBy::id('code_suggest'));
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

            throw new HttpException($e->getMessage());
        }

        try {
            $session->wait(5, 500)->until(function() use($session, &$wait) {
                $element = $session->findElement(WebDriverBy::className('modules_stock'));
                if ($element) {
                    return true;
                }
            }, '读取失败!');
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        // 获取AJAX内容
        try {
            $values = $session->executeScript("return $('.xgstock').find('tr:first').data('stockdata');", [$element]);
            if ($values == null) {
                return false;
            }
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        // 分解异步获取内容
        $code = $values['Code'];

        return $code;
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
                return str_replace('{code}', $code, $paths[1]);
            });
        }

        // 访问对象
        $session = $this->driver->handler();

        // 获取结果
        try {
            $values = $session->executeScript("function g(){var a=[],b={},e={},f={},g={};b.name='\u5f53\u524d';b.value=\$('.quote_quotenums .zxj').text();b.amplitude=\$('.quote_quotenums .zd').find('span>span').eq(0).text();b.variation=\$('.quote_quotenums .zd').find('span>span').eq(1).text();a.push(b);e.name='\u6807\u9898';e.value=\$('.quote_title_name').attr('title');a.push(e);f.name='\u4ee3\u7801';f.value=\$('.quote_title_code').text();a.push(f);g.name='\u65f6\u95f4';g.value=\$('.quote_title_time').text();a.push(g);\$('.brief_info_c').find('td').each(function(c,
            d){c={};d=\$(d).text().replace(/<[^>]+/g,'').split(':');c.name=d[0];c.value=d[1].replace(/[\s]+/g,'');a.push(c)});return a}return g();");
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
            '昨收' => 'close',// 昨收盘,
            '今开' => 'open', //今开盘
            '最高价' => 'high',// 最高价
            '最低价' => 'lowest',// 最低价
            '当前' => 'value', // 当前价
            '成交额' => 'turnover',// 成交额(万)
            '成交量' => 'volume',// 成交量(万)
            '总股本' => 'tmc',// 总市值
            '港市值' => 'cmv',// 流通市值
        ];

        foreach($values as $value) {
            if($value['name'] == '时间') {
                $data['datetime'] = mb_substr($value['value'], 1, mb_strlen($value['value']) - 2);
                $datetime = explode(' ', $data['datetime']);
				if (count($datetime) > 1) {
					$data['date'] = $datetime[0];
					$data['time'] = $datetime[1];
				}
                $data['timestamp'] = strtotime($data['datetime']);
            } elseif($value['name'] == '当前') {
                $data['value'] = $value['value'];// 当前价
                $data['amplitude'] = $value['amplitude'];// 幅值
                $data['ratio'] = str_replace('%', '', $value['variation']);// 幅率
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
        $data['cmv'] = (float)$data['cmv'];
        $data['volume'] = (float)$data['volume'];

        if ($data['cmv'] != 0 && $data['volume'] != 0) {
            $data['chands'] = round(($data['volume'] / $data['cmv']) * 100, 3); // 换手 = 成交量÷流通股本×100%
        }

        $currency = $this->getCurrency();

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
    private function getCurrency()
    {
        set_time_limit(9999);

        $this->driver->open($this->driver->routes(2));
        // 访问对象
        $session = $this->driver->handler();
        // 获取结果
        try {
            $values = $session->executeScript("function g(){var a=[],b={},e={},f={},g={};b.name='\u5f53\u524d';b.value=\$('.quote_quotenums .zxj').text();b.amplitude=\$('.quote_quotenums .zd').find('span>span').eq(0).text();b.variation=\$('.quote_quotenums .zd').find('span>span').eq(1).text();a.push(b);e.name='\u6807\u9898';e.value=\$('.quote_title_name').attr('title');a.push(e);f.name='\u4ee3\u7801';f.value=\$('.quote_title_code').text();a.push(f);g.name='\u65f6\u95f4';g.value=\$('.global_li2').text();a.push(g);\$('.gi_quote_list').find('li').each(function(c,
            d){c={};d=$(d).text().replace(/<[^>]+/g,'').split(':');c.name=d[0];c.value=d[1].replace(/[\s]+/g,'');a.push(c)});return a}return g();");
        } catch(\Exception $e) {
            throw new HttpException($e->getMessage());
        }

        if ($values == null) {
            return false;
        }

        $mapper = [
            '标题' => 'name',
            '当前' => 'value',
        ];

        $currency = [];
        $currency['channel'] = $this->channel;

        foreach($values as $value) {
            if($value['name'] == '时间') {
                $value['value'] = str_replace('北京 ', date('Y-', TIMESTAMP), $value['value']);
                $value['value'] = str_replace('月', '-', $value['value']);
                $value['value'] = str_replace('日', '', $value['value']);
                $currency['datetime'] = $value['value'];
                $datetime = explode(' ', $currency['datetime']);
                $currency['date'] = $datetime[0];
                $currency['time'] = $datetime[1];
                $currency['timestamp'] = strtotime($currency['datetime']);
            } elseif($value['name'] == '当前') {
                $currency['value'] = $value['value'];// 当前价
                $currency['ratio'] = str_replace('%', '', $value['variation']);// 幅率
            } elseif(isset($mapper[$value['name']])) {
                $currency[$mapper[$value['name']]] = $value['value'];
            }
        }

        redis()->set('te.currency', $currency);
        $currency['timestamp'] = TIMESTAMP;

        $this->currency = $currency;
        $this->driver->back();

        return $currency;
    }
}