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

class Ths extends AppService
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
    private $channel = 'ths';

    /**
     * 接口列表
     *
     * @var array
     */
    protected $paths = [
        'http://stock.10jqka.com.cn/hks/',
        'http://news.10jqka.com.cn/public/index_keyboard_{keyword}_hk_5_jsonp.html',
        'http://stockpage.10jqka.com.cn/HK{code}/',
        'http://d.10jqka.com.cn/v6/realhead/hk_HK{code}/defer/last.js',
        'http://fe.10jqka.com.cn/'
    ];

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
     * 获取可用的CODE
     *
     * @param string $code
     * @return string
     */
    private function decode_code($code)
    {
        $code = ltrim($code, '0');
        if (strlen($code) < 4) {
            return substr('0000' . $code, -4);
        }

        return $code;
    }

    /**
     * 获取大盘信息
     *
     * @param string $value
     * @return array
     */
    public function getMarket($value = '')
    {
        if ($value === '') {
            $values = $this->driver->execute("var gurs=[];function gur(us,i){if(us.length==gurs.length){return gurs}\$.ajax({url:us[i],async:false,complete:function(r){gurs.push(gurp(r.responseText))}});return gur(us,i+1)}function gurp(s){var p=s.indexOf('{');return s.substr(p,s.length-p-1)}return gur(['http://d.10jqka.com.cn/v6/time/hk_HSI/last.js','http://d.10jqka.com.cn/v6/time/hk_HSCCI/last.js','http://d.10jqka.com.cn/v6/time/hk_HSCEI/last.js'],0)");
            $result = [];
    
            foreach($values as $value) {
                $result[] = $this->getMarket($value);
            }
    
            return $result;
        } else {
            $value = json_decode($value, true);
            $result = null;

            if ($value != null) {
                $result = [];
                $result['channel'] = $this->channel;
                foreach($value as $key => $val) {
                    $result['code'] = str_replace('hk_', '', $key);
                    $result['title'] = $val['name'];
                    $result['shortname'] = $val['name'];
                    $result['close'] = (float)$val['pre']; // 昨收盘
                    $result['date'] = substr($val['date'], 0, 4) . '-' . substr($val['date'], 4, 2) . '-' . substr($val['date'], 6, 2); // 日期

                    $val['data'] = explode(';', $val['data']);
                    $val['origin'] = end($val['data']);
                    $val['last'] = explode(',', $val['origin']);

                    $time = array_shift($val['last']);
                    $result['time'] = substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':00';
                    $result['datetime'] = $result['date'] . ' ' . $result['time'];
                    $result['value'] = array_shift($val['last']);
                    $result['amplitude'] = round($result['value'] - $result['close'], 6);
                    $result['amplitude_value'] = round($result['amplitude'] / $result['close'] * 100, 6);
                    $result['original'] = $val['origin'];
                    $result['timestamp'] = strtotime($result['datetime']);
                }
            }

            return $result;
        }
    }

    /**
     * 模糊搜索返回代码
     *
     * @param string $keyword
     * @return void
     */
    public function getCode($keyword, $retry = false)
    {
        $url = str_replace('{keyword}', urlencode($keyword), $this->driver->routes(1));
        $values = $this->driver->execute("var gurs=[];function gur(us,i){if(us.length==gurs.length){return gurs[0]}\$.ajax({url:us[i],async:false,complete:function(r){gurs.push(gurp(r.responseText))}});return gur(us,i+1)}function gurp(s){return JSON.parse(s.substr(6,s.length-7))}return gur(['{$url}'],0)");
 
        // 搜索多个结果只取第一个结果
        foreach($values as $value) {
            $value = explode(' ', $value);
            $codes = explode('||', array_shift($value));
            $code = floatval($codes[1]);

            return $this->format_code($code);
        }

        if ($retry == false) {
            sleep(1);
            return $this->getCode($keyword, true);
        }
    }

    /**
     * 返回行情信息
     * quotebridge_v6_realhead_hk_HK6908_defer_last({"items":{"407":"573019000.000","402":"573019000.000","527198":"280000.000","10":"3.920","24":"3.890","25":"9000.000","30":"3.920","31":"57000.000","8":"4.000","9":"3.820","13":"1262000.000","19":"4942870.000","7":"3.950","15":"268000.000","14":"982000.000","69":"4.140","70":"3.940","223":"","224":"","225":"2079190.000","226":"581500.000","237":"27520.000","238":"46940.000","259":"1746040.000","260":"415190.000","38":"-1","37":"-1","39":"-1","23":"","22":"","90":"","92":"","254":"","278":"-8000.000","49":"29000.000","271":21,"51":"","276":"0","277":"0","12":"5","17":"","95":"","96":"","273":"-1","274":"","74":"0.000","75":"0.000","85":"","127":"","130":"","264648":"0.070","199112":"1.82","2942":"","1968584":"0.220","2034120":"","1378761":"3.917","526792":"4.675","395720":"-174000.000","461256":"-59.589","3475914":"2246234500.000","3541450":"2246234500.000","1149395":"3.327","1771976":"1.828","134152":"0.000","592920":"3.327","3153":"","6":"3.850","66":"","hkStatus":3,"stop":0,"time":"2022-09-08 11:15:21 \u5317\u4eac\u65f6\u95f4","name":"\u5b8f\u5149\u534a\u5bfc\u4f53","marketType":"","5":"HK6908","stockStatus":"--","marketid":"177","updateTime":"2022-09-08 11:15"}})
     *
     * @param string $code
     * @param boolean $opend
     * @return array
     */
    public function getQuotes($code, $opend = true)
    {
        $code = $this->decode_code($code);

        if ($opend) {
            $this->driver->open(function($paths) use($code) {
                return str_replace('{code}', $code, $paths[2]);
            });
        }

        $data = [];
        $currency = [];
        $url = str_replace('{code}', $code, $this->driver->routes(3));
        $values = $this->driver->execute("var gurx=[];function gur(us,i){if(us.length==gurx.length){return gurx[0]}\$.ajax({url:us[i],async:false,complete:function(r){gurx.push(gurp(r.responseText))}});return gur(us,i+1)}function gurp(s){var p=s.indexOf('{');return JSON.parse(s.substr(p,s.length-p-1))}return gur(['{$url}'],0)");

        if (is_array($values) && isset($values['items'])) {
            $values = $values['items'];
            $data['channel'] = $this->channel;
            $data['code'] = $code;
            $data['title'] = $values['name'];
            $data['datetime'] = $values['updateTime'];
            $values['updateTime'] = explode(' ', $values['updateTime']);
            $data['date'] = $values['updateTime'][0];
            $data['time'] = $values['updateTime'][1];
            $data['timestamp'] = strtotime($data['datetime']);
            $data['value'] = $values[10];           // 当前价
            $data['close'] = $values[6];            // 昨收盘
            $data['lowest'] = $values[9];           // 最低价
            $data['open'] = $values[7];             // 今日开盘价
            $data['high'] = $values[8];             // 最高价
            $data['volume'] = $values[13];          // 成交量
            $data['turnover'] = $values[19];        // 成交额

            $values = array_values($values);

            $data['amplitude'] = $values[29];       // 幅值
            $data['ratio'] = $values[14];           // 幅率
            $data['chands'] = $values[13];          // 换手
            $data['vibration'] = $values[52];       // 振幅
            $data['tmc'] = $values[40];             // 总市值
            $data['cmv'] = $values[41];             // 流通市值
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
        $this->driver->open($this->driver->routes(4));

        $values = $this->driver->execute("function getCurrency(){var keys=[],values=[];\$('.toggle-table>thead>tr')[0].childNodes.forEach(function(v){if(v.nodeName=='TD')keys.push(v.innerText)}),\$('.toggle-table>tbody>tr').each(function(i,v){if(typeof values[i]=='undefined')values.push({});var ind=0;v.childNodes.forEach(function(c,k){if(c.nodeName=='TD'){values[i][keys[ind]]=c.innerText;ind++}})});return values}return getCurrency();");
        $currency = [];

        if (is_array($values)) {
            foreach($values as $value) {
                if (strpos($value["货币名称"],'港币')!==false) {
                    $currency['channel'] = $this->channel;
                    $currency['date'] = date('Y-m-d');       // 日期
                    $currency['time'] = date('H:i');         // 时间
                    $currency['name'] = $value["货币名称"];   // 名称: 港元人民币
                    $currency['value'] = $value['现钞买入价']; // 当前价
                    $currency['datetime'] = $currency['date'] . ' ' . $currency['time'];
                    $currency['timestamp'] = TIMESTAMP;
                }
            }
        }

        redis()->set('te.currency', $currency);
        $this->driver->back();

        return $currency;
    }
}