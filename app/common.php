<?php
// 应用公共文件
if (!function_exists('array_splice_value')) {
    /**
     * 删除数组中的一个元素
     * @version 1.0.0
     * @return mixed
     */
    function array_splice_value(&$arr, $index = 0, $length = 1, $replace = []) {
        $pre = array_splice($arr, $index, $length, $replace);
        return current($pre);
    }
}

if (!function_exists('password_check')) {
    /**
     * 密码校验
     * @param String $ori
     * @param String $salt
     * @param String $verify
     * @return String
     */
    function password_check($ori = '', $salt = '', $verify = null) {
        if ($verify === null) {
            return md5(md5($verify) . $salt) !== $ori;
        } else {
            if ($salt === '') {
                $salt = uniqid();
                return [md5(md5($ori) . $salt), $salt];
            }

            return md5(md5($salt) . $verify);
        }
    }
}

if (!function_exists('getToken')) {
    /**
     * 获取TOKEN
     * @param String $prepare
     * @return String
     */
    function getToken($prepare = '') {
        return md5(uniqid() . "$" . $prepare);
    }
}

if (!function_exists('rand_string')) {
    /**
     * 获取随机字串
     * @param String $prepare
     * @return String
     */
    function rand_string($length=5, $indent=0) {
        $dict = array(
            '_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '0123456789',
            'abcdefghijklmnopqrstuvwxyz',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'in:simplified',
            'in:traditional'
        );
        if(substr($dict[$indent] , 0 , 3) == 'in:'){
            require_once(dirname(__FILE__).'/functions/Tradition/'.substr($dict[$indent] ,3).'.php');
            $_t = tradition.'_'.substr($dict[$indent],3);
            $dict[$indent] = $t();
        }
        $result = '';
        while($length-- > 0)$result .= substr($dict[$indent] ,mt_rand(0 ,strlen($dict[$indent])-1) ,1);
        return $result;
    }
}

if (!function_exists('redis')) {
    /**
     * 应用Redis存储
     * @return std
     */
    function redis() {
        return \think\facade\Cache::store('redis');
    }
}

if (!function_exists('fileCache')) {
    /**
     * 返回相应的缓存
     *
     * @param string $name
     * @param function $fallback
     * @return void
     */
    function fileCache(string $name, $fallback, $force = false) {
        static $cache = [];

        if (!isset($cache["fc.{$name}"]) || $force === true) {
            $data = cache("fc.{$name}");

            if (!$data || $force === true) {
                if (is_callable($fallback)) {
                    $data = $fallback($name);
                } else {
                    $data = $fallback;
                }

                cache("fc.{$name}", $data, 31536000);
            }

            $cache["fc.{$name}"] = $data;
        }

        return $cache["fc.{$name}"];
    }
}

if (!function_exists('array_keys_filter')) {
    /**
     * 数组键名过滤
     * @param Array $stack
     * @param Array $filters
     * @return Array
     */
    function array_keys_filter(Array $stack, $filters) {
        if (is_string($filters)) {
            $filters = [$filters];
        }

        foreach($stack as $key => $value) {
            if (preg_match('/[\w\-]+\[[\w\-]+\]/', $key)) {
                $keys = explode('[', $key);
                if (!isset($stack[$keys[0]])) {
                    $stack[$keys[0]] = [];
                }

                $keys[1] = substr($keys[1], 0, strlen($keys[1]) - 1);
                $stack[$keys[0]][$keys[1]] = $value;

                unset($stack[$key]);
            }
        }

        $res = [];

        foreach($filters as $filter) {
            if (is_array($filter)) {
                if (is_string($filter[0])) {
                    if (isset($stack[$filter[0]])) {
                        $res[$filter[0]] = $stack[$filter[0]];
                    } else {
                        $res[$filter[0]] = $filter[1];
                    }
                }
            } elseif(is_string($filter)) {
                if (isset($stack[$filter])) {
                    $res[$filter] = $stack[$filter];
                }
            }
        }

        return $res;
    }
}

if (!function_exists('parseTree')) {
    /**
     * 无线级分解
     * 
     * @param array $data 数据源
     * @param string $id 主键
     * @param string $parentId 父级
     * @param string $children 子类
     * @return Array
     */
    function parseTree(Array $data, $id = "id", $parentId = 'parent_id', $children = 'children')
    {
        $rows = $res = [];
        foreach ($data as $row)
            $rows[$row[$id]] = $row;

        foreach ($rows as $row) {
            if (isset($rows[$row[$parentId]])) {
                $rows[$row[$parentId]][$children][] = &$rows[$row[$id]];
            } else if($row[$parentId] == 0){
                $res[] = &$rows[$row[$id]];
            }
        }

        return $res;
    }
}

if (!function_exists('forMapIds')) {
    /**
     * 无限向下遍历树形
     *
     * @param collect $model
     * @param string  $pk
     * @return array [1,2,3,4,5,6]
     */
    function forMapIds($m, $value, $pk = 'id', $pid = 'parent_id') {
        // 当前所有IDS
        $ids = $m->where($pid, $value)->value("GROUP_CONCAT(`{$pk}`)");

        if (!empty($ids)) {
            $cids = $ids;

            while(!empty($cids)) {
                $cids = $m->where($pid, 'IN', $cids)->value("GROUP_CONCAT(`{$pk}`)");
                if (!empty($cids)) {
                    $ids .= ",{$cids}";
                }
            }
        }

        return explode(",", $ids);
    }
}