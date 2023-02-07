<?php
namespace mashroom\provider;
/**
 * 应用请求对象类
 * @package mashroom.provider
 */
use think\App;

class Request extends \think\Request
{
    // 用户登录信息存储
    public $user = null;

    /**
     * ACCESS TOKEN
     * 
     * @return Boolean
     */
    public function checkAccessToken() {
        if (!((bool)$this->user)) {
            return false;
        } elseif(is_array($this->user) && !isset($this->user['SESSION_ID'])) {
            return false;
        }

        return true;
    }

    /**
     * ACCESS TOKEN
     * 
     * @return Boolean
     */
    public function checkLogin() {
        if (!((bool)$this->user)) {
            return false;
        } elseif(is_array($this->user) && !isset($this->user['user_id'])) {
            return false;
        } elseif(!$this->exists_user($this->user['user_id'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate user
     *
     * @param string $appid
     * @return void
     */
    private function exists_user($appid)
    {
        $partners = config('common.partner');
        $appids = array_values(array_column($partners, 'appid'));

        return in_array($appid, $appids);
    }

    /**
     * 当前是否JSON请求
     * @access public
     * @return bool
     */
    public function isJson(): bool
    {
        if (config('app.response_data_type') == 'json') {
            return true;
        }
        
        return parent::isJson();
    }

    /**
     * 设置为登录用户
     * @return mixed
     */
    public function login($user)
    {
        if (!is_array($user)) {
            $user = $user->toArray();
        }

        $this->user = array_merge($this->user, $user);
        // 缓存登录信息
        $expireTime = config('admin.user_logged_stay', 1) * 3600;
        redis()->set("usr.{$this->user['SESSION_ID']}", $this->user, $expireTime);

        return [
            'ign' => TIMESTAMP,
            'token' => $this->user['SESSION_ID']
        ];
    }

    /**
     * 设置当前请求的pathinfo
     * @access public
     * @param  string $pathinfo
     * @return $this
     */
    public function setPathinfo(string $pathinfo)
    {
        $path = $this->pathinfo();

        // var_dump($pathinfo);
        $map  = config('app.app_map', []);
        $name = current(explode('/', $path));

        if (isset($map[$name])) {
            $pathinfo = $name . '/' . $pathinfo;
        }

        $this->pathinfo = $pathinfo;
        return $this;
    }
}
