<?php
namespace app\api\controller\driver;
/**
 * 登录校验
 * 
 * @date: 2021-05-10 20:19:31
 * @version 1.0.1
 * @package driver
 */
use app\api\Controller;
use app\api\model\UserModel;
use think\facade\Cookie;

class Auth extends Controller
{
    /**
     * 测试
     *
     * @return void
     */
    public function test()
    {
        $value = "0000000000000hello";
        $value = ltrim($value, '0');
        $value = 10E5;
        var_dump($value);die;

        return $this->success($value);
    }

    /**
     * 创建一枚可通讯的令牌
     * @return void
     */
    public function buildGenerateToken()
    {
        $token = cookie('token');

        if (!$token) {
            $token = getToken();
            $value = [
                'SESSION_ID' => $token,
                'REMOTE_ADDR' => $this->request->ip()
            ];
            redis()->set("usr.{$token}", $value);
            cookie('token', $token);
        } else {
            $value = redis()->get("usr.{$token}");

            if ($value == null) {
                setcookie('token', '', TIMESTAMP-36000, '/');
                header('refresh: 1');
                exit();
            }
        }

        return $this->success($value);
    }

    /**
     * 登录
     *
     * @return void
     */
    public function login()
    {
        $data = array_keys_filter($this->request->post(), [
            ['appId', ''],
            ['sign', ''],
            ['ticket', '']
        ]);

        $partner = $this->app->make(UserModel::class)->find($data['appId']);
        if (!$partner) {
            return $this->fail("appId [不存在]");
        }

        $sign = md5($data['ticket'] . '&date=' . date('Ymd') . '&key=' . $partner['secret']);
        if ($sign != $data['sign']) {
            // return $this->fail("提交参数错误");
        }

        $partner['group_access'] = $partner->getGroupAccess();

        $ret = $this->success($this->request->login($partner));
        $partner['token'] = $this->request->user['SESSION_ID'];
        $partner->save();

        // 记录日志
        $this->app->make(\app\api\model\LogsModel::class)->info('logs.user.login', 'LOGGED', $this->request->user);

        return $ret;
    }

    /**
     * 添加新用户
     *
     * @return void
     */
    public function addUser()
    {
        $data = array_keys_filter($this->request->post(), [
            ['username', ''],
            ['group', '']
        ]);

        if ($data['username'] == '') {
            $this->fail('用户名不能为空!');
        } elseif($data['group'] == '') {
            $this->fail('用户组不能为空!');
        }

        $user = $this->app->make(UserModel::class)->insert($data);

        return $this->success($user);
    }

    /**
     * 注解登录
     * @return mixed
     */
    public function logout()
    {
        // 记录日志
        $this->app->make(\app\api\model\LogsModel::class)->info('logs.user.logout', 'LOGOUT', $this->request->user);
        // 去除缓存记录
        redis()->delete("usr.{$this->request->user['token']}");

        $this->request->cookie('token', null);
        $this->request->user = null;

        return $this->success();
    }

    /**
     * 获取登录信息
     *
     * @return void
     */
    public function getInfo()
    {
        return $this->success($this->request->user);
    }
}