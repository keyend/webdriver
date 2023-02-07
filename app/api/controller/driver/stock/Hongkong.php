<?php
namespace app\api\controller\driver\stock;
/**
 * @date: 2021-05-10 20:19:31
 * @version 1.0.1
 * @package driver
 */
use app\api\Controller;

class Hongkong extends Controller
{
    /**
     * 初始化
     *
     * @return void
     */
    protected function initialize()
    {
        $this->driver = $this->app->get('mushroom')->webDriver($this->request->get('protocol'));
    }

    /**
     * 关键字搜索
     *
     * @param string $keyword
     * @return void
     */
    public function search()
    {
        $keyword = $this->request->get('kw');
        $response = null;

        if ($keyword != '') {
            $response = $this->driver->getCode($keyword);
            if (!$response || $response == '') {
                return $this->fail();
            }
        }

        return $this->success(['code' => $response]);
    }

    /**
     * 获取最新行情
     *
     * @param string $code
     * @return void
     */
    public function quotes($code = '')
    {
        $response = null;

        if ($code != '') {
            $response = $this->driver->getQuotes($code);
        }

        return $this->success($response);
    }

    /**
     * 获取大盘信息
     *
     * @return void
     */
    public function market()
    {
        return $this->success($this->driver->getMarket());
    }

    /**
     * 获取设置接口
     *
     * @return void
     */
    public function channel()
    {
        if ($this->request->isPost()) {
            $this->driver->setChannel($this->request->post('name'));
            return $this->success();
        } else {
            return $this->success($this->driver->getChannels());
        }
    }

    /**
     * 获取外汇信息
     *
     * @return void
     */
    public function foreign()
    {
        $response = $this->driver->getForeignExchange();
        return $this->success($response);
    }
}