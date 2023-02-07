<?php
namespace app\api\model;

use think\facade\Log;
use think\facade\Config;
use mashroom\provider\Model;
use mashroom\exception\HttpException;

class UserModel extends Model
{
    /**
     * 查找用户
     *
     * @param string $appid
     * @return void
     */
    public function find($appid)
    {
        foreach (config('common.partner') as $i => $partner) {
            if ($partner['appid'] == $appid) {
                foreach($partner as $key => $val) {
                    $this->setAttr($key, $val);
                }

                $this->setAttr('user_id', $appid);
                $this->setAttr('lastonline_time', TIMESTAMP);

                break;
            }
        }

        return $this;
    }

    public function save(array $data = [], string $sequence = null): bool
    {
        $configs = config('common');
        foreach($configs['partner'] as $i => $partner) {
            if ($partner['appid'] == $this->getAttr('appid')) {
                $origin = $partner;
                $configs['partner'][$i] = $this->getData();
            }
        }

        // 记录日志
        app()->make(LogsModel::class)->info('logs.sys.partner.update', 'UPDATED', [$origin, $this->getData()]);

        // 当前项目路径
        $path = app()->getAppPath();
        $content = "<?php\r\nreturn " . var_export($configs, true) . ";\r\n";

        if (!@file_put_contents($path . 'config' . DIRECTORY_SEPARATOR . "common.php", $content)) {
            Log::write("更新写入配置[" . AP_BRANCH . ".{$name}}]失败");
        }

        Config::set($configs, 'common');

        return true;
    }

    public function insert($data)
    {
        $configs = config('common');
        $data['appid'] = uniqid();
        $data['secret'] = md5($data['appid'] . "#" . TIMESTAMP);
        $data['create_time'] = TIMESTAMP;

        $group = $this->getGroup($data['group']);
        if (!$group) {
            throw new \HttpException("用户组[{$data['group']}]不存在!");
        }

        foreach($configs['partner'] as $i => $partner) {
            if ($partner['username'] == $data['username']) {
                throw new \HttpException("用户[{$data['username']}]已存在!");
            }
        }

        $configs['partner'][] = $data;
        // 当前项目路径
        $path = app()->getAppPath();
        $content = "<?php\r\nreturn " . var_export($configs, true) . ";\r\n";

        if (!@file_put_contents($path . 'config' . DIRECTORY_SEPARATOR . "common.php", $content)) {
            Log::write("更新写入配置[" . AP_BRANCH . ".{$name}}]失败");
        }

        Config::set($configs, 'common');

        return $data;
    }

    private function getGroup($name)
    {
        foreach(config('common.groups') as $group) {
            if ($name == $group['name']) {
                return $group;
            }
        }
    }

    /**
     * 返回权限列表
     *
     * @return array
     */
    public function getGroupAccess()
    {
        foreach (config('common.groups') as $group) {
            if ($group['name'] == $this->getAttr('group')) {
                return $group['access'];
            }
        }
    }
}
