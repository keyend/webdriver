<?php
/**
 * mashroom.service.WebDriverService
 * 
 * @version 1.0.0
 */
namespace mashroom\service;

use mashroom\exception\HttpException;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\JavascriptErrorException;

class WebDriverService extends AppService
{
    // 保留的历史记录条数
    const history_stock_max = 10;

    // 保留COOKIE时间
    const expired = 1800;

    /**
     * 连接设备
     *
     * @var object
     */
    private $driver;

    /**
     * 会话ID
     *
     * @var string
     */
    private $session_id;

    /**
     * 缓存的COOKIE
     *
     * @var array
     */
    private $cookies;

    /**
     * 打开的历史界面
     *
     * @var array
     */
    private $hrefs;

    /**
     * 初始化状态
     *
     * @var boolean
     */
    private $initialized = false;

    /**
     * 应用的接口名
     *
     * @var string
     */
    private $protocolName;

    /**
     * 应用的接口
     *
     * @var string
     */
    private $protocol;

    /**
     * 应用的接口
     *
     * @var array
     */
    private $protocolInfo;

    private function initialize($retry = false)
    {
        $this->session_id = redis()->get('te.session_id');
        $this->initialized = true;

        try {
            if ($this->session_id != null) {
                // load session interface
                $this->driver = RemoteWebDriver::createBySessionID($this->session_id, $this->service_url);
            } else {
                $desiredCapabilities = DesiredCapabilities::chrome();

                // use interfaceless operation when non-WINNT OS
                // if (PHP_OS != 'WINNT') {
                    $chromeOptions = new ChromeOptions();
                    $chromeOptions->addArguments([
                        // '--headless',
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu'
                    ]);
                    $chromeOptions->addArguments(['--window-size=1960,1080']);
                    $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
                // }

                // create data interface
                $this->driver = RemoteWebDriver::create($this->service_url, $desiredCapabilities);
                $this->session_id = $this->driver->getSessionID();

                redis()->set('te.session_id', $this->session_id);
            }
        } catch(\Exception $e) {
            $this->logger->info('instance of failed', 'ERROR', [$e->getCode(), $e->getMessage()]);

            if ($e instanceof WebDriverCurlException) {
                throw new HttpException('连接失败!', 40001);
            } elseif($e instanceof InvalidSessionIdException) {
                redis()->delete('te.session_id');
                return $this->initialize();
            } else {
                throw new HttpException('载入时出错!', 40000);
            }
        }

        try {
            $check = $this->get('1');
        } catch(\Exception $e) {
            if($e instanceof InvalidSessionIdException) {
                redis()->delete('te.session_id');
                $this->quit();
                return $this->initialize(true);
            } elseif(strpos($e->getMessage(), 'not reachable') !== false) {
                redis()->delete('te.session_id');
                $this->quit();
                return $this->initialize(true);
            } else {
                throw new HttpException('加载会话失败!', 40000);
            }
        }

        if ($check != '1') {
            if ($retry == false) {
                if (is_array($check) && $check['error'] == 'invalid session id') {
                    redis()->delete('te.session_id');
                }

                $this->quit();
                return $this->initialize(true);
            }
        }

        $this->hrefs = redis()->get('te.history');
        if ($this->hrefs == null) {
            $this->hrefs = [];
        }

        $this->cookies = $this->getCookie();

        $last = end($this->hrefs);
        if ($last != null) {
            $location = $this->getLocation();
            if ($location['href'] != $last) {
                $this->hrefs = [];
                $this->hrefs[] = $location['href'];
                $last = $location['href'];
            }
        }

        if ($last == 'data:,') {
            if (false == $retry) {
                $this->quit();
                return $this->initialize(true);
            }
        }

        if ($last != $this->paths[0]) {
            $this->back();
        }
        
        if (empty($this->hrefs)) {
            $this->push($this->paths[0]);
        }
    }

    /**
     * 返回界面COOKIE
     *
     * @return void
     */
    public function getCookie()
    {
        if ($this->cookies != null) {
            if (is_array($this->cookies) && $this->cookie['expired'] > TIMESTAMP) {
                return $this->cookies;
            }
        }

        $this->cookies = redis()->get('te.cookie');
        if ($this->cookies == null) {
            $cookieString = $this->open($this->paths[0])->executeScript('return document.cookie;');
            $this->cookies = [
                'expired' => TIMESTAMP + self::expired,
                'values' => \GuzzleHttp\Cookie\SetCookie::fromString($cookieString)->toArray()
            ];
            redis()->set('te.cookie', $this->cookies);
        }

        return $this->cookies;
    }

    /**
     * 清空访问
     *
     * @return void
     */
    public function quit()
    {
        $this->cookies = null;
        $this->hrefs = null;

        redis()->delete('te.history');
        redis()->delete('te.cookie');

        if ($this->driver != null) {
            $this->driver->quit();
        }
    }

    /**
     * 设置默认接口
     *
     * @param string $value
     * @return void
     */
    public function setChannel($value)
    {
        foreach ($this->getChannels() as $i => $channel) {
            if (strtolower($channel['value']) == strtolower($value)) {
                redis()->set('te.channel', $i);
                $this->quit();
            }
        }
    }

    /**
     * 返回默认接口
     *
     * @return void
     */
    public function getChannel()
    {
        return (int)redis()->get('te.channel');
    }

    /**
     * 构造函数
     * 
     * @param string $name
     */
    public function __construct($name = '')
    {
        parent::__construct();

        $this->protocolInfo = $this->getChannels($this->getChannel());
        $this->protocolName = $this->protocolInfo['value'];
    }

    /**
     * 返回访问路由列表
     *
     * @param integer $index
     * @return void
     */
    public function routes($index = null)
    {
        if ($index == null) {
            return $this->paths;
        }

        return $this->paths[$index];
    }

    /**
     * 添加历史记录
     *
     * @param string $url
     * @return void
     */
    private function push($url = '')
    {
        $this->hrefs[] = $url;

        if (count($this->hrefs) > self::history_stock_max) {
            array_shift($this->hrefs);
        }

        redis()->set('te.history', $this->hrefs);

        return $this;
    }

    /**
     * 打开界面
     *
     * @param string $url
     * @return void
     */
    public function open($url = '')
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (is_callable($url)) {
            $url = $url($this->paths);
        }

        if ($url != '') {
            $last = end($this->hrefs);
            if ($last != $url) {
                $this->push($url);
                $this->driver->get($url);
            }
        }

        return $this->driver;
    }

    /**
     * 后退
     *
     * @return void
     */
    public function back()
    {
        array_shift($this->hrefs);
        redis()->set('te.history', $this->hrefs);
        $this->driver->navigate()->back();
    }

    /**
     * 返回操作对象
     *
     * @return void
     */
    public function handler()
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->driver;
    }

    /**
     * 返回Location对象
     *
     * @return void
     */
    public function getLocation()
    {
        return $this->get('location', 'json');
    }

    /**
     * 返回内容
     *
     * @param string $str
     * @return void
     */
    public function execute($str = '')
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        try {
            $values = $this->driver->executeScript($str);
        } catch(JavascriptErrorException $e) {
            $this->app->make(\app\api\model\LogsModel::class)->info('com.service.exponent', 'ERROR', $e->getMessage());
            return null;
        }

        return $values;
    }

    /**
     * 返回页面内容
     *
     * @param string $name
     * @param string $typeof
     * @return void
     */
    public function get($name, $typeof = 'string')
    {
        if ($typeof == 'json') {
            $values = json_decode($this->execute("return JSON.stringify({$name});"), true);
        } elseif($typeof == 'array') {
            $values = json_decode($this->execute("return {$name};"), true);
        } else {
            $values = $this->execute("return {$name};");
        }

        return $values;
    }

    /**
     * 获取接口列表
     *
     * @param int $index
     * @return array
     */
    public function getChannels($index = '')
    {
        $files = glob(__DIR__ . DIRECTORY_SEPARATOR . 'webdriver' . DIRECTORY_SEPARATOR . '*.php');
        $result = [];

        foreach($files as $i => $file) {
            $result[] = [
                'title' => "数据接口" . ($i + 1),
                'value' => basename($file, '.php')
            ];
        }

        return $index === '' ? $result : $result[$index];
    }

    /**
     * 魔术访问
     *
     * @param string $name
     * @param array $arguments
     * @return void
     */
    public function __call($name, $arguments = [])
    {
        if ($this->protocol == null) {
            $className = "\\mashroom\\service\\webdriver\\" . ucfirst($this->protocolName);
            if (!class_exists($className)) {
                throw new HttpException("服务[Service::{$this->protocolName}]不存在!");
            }
    
            $this->protocol = self::instance($className, [$this]);
            $this->paths = $this->protocol->getPaths();
            $this->service_url = $this->protocol->getHost();
        }

        if (!method_exists($this->protocol, $name)) {
            throw new HttpException("服务[Service::{$this->protocolName}.{$name}]方法不存在!");
        }

        $this->protocol->before();

        $result = call_user_func_array([$this->protocol, $name], $arguments);

        $this->protocol->after();

        return $result;
    }
}