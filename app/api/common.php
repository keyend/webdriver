<?php
// 应用公共文件

if (!function_exists('array_column_bind')) {
    /**
     * 数据集多列绑定到一行上
     * @param 数据集 $list
     * @param 字段名 $field
     * @param 字段值 $value
     * @return Array
     */
    function array_column_bind(&$list, $exten, $field, $value) {
        array_walk($list, function(&$arr) use($exten, $field, $value) {
            foreach ($arr[$exten] as $attr) {
                $arr[$attr[$field]] = $attr[$value];
            }
        
            unset($arr[$exten]);
        });

        return $list;
    }
}

if (!function_exists('extractTree')) {
    /**
     * 分解树形数组
     * @param array $data 数据源
     * @return Array
     */
    function extractTree($data, $sortField='', $sort=SORT_ASC) {
        $rows = [];
        array_walk($data, $parse = function($arr) use (&$rows, &$parse) {
            if (is_array($arr) && !empty($arr)) {
                if (isset($arr['children'])) {
                    array_walk($arr['children'], $parse);
                    unset($arr["children"]);
                }

                $rows[] = $arr;
            }
        });

        if ($sortField) {
            $volume = array_values(array_column($rows, $sortField));
            array_multisort($volume, $sort, $rows);
        }

        return $rows;
    }
}

if (!function_exists('formToData')) {
    /**
     * 将FormBuilder表单转为JSON Array
     * @param Elm::Form $form
     * @param string    $type 表单类型 新增、更新
     * @return array
     */
    function formToData($form, $type = 'create'): array
    {
        $action = $form->getAction();
        $method = $form->getMethod();
        $title = $form->getTitle();
        $config = (object)$form->formConfig();
        $rule = $form->formRule();

        return compact('rule', 'action', 'method', 'title', 'config');
    }
}

if (!function_exists('parseTime')) {
    /**
     * 解析时间
     * @return String
     */
    function parseTime($value) {
        if (!$value) return '';
        if (!is_numeric($value)) $value = strtotime($value);

        $language = \think\facade\Lang::getLangSet();
        $format = 'Y-m-d H:i';
        if ($language === 'ja') {
            $value += 3600;
            $format = 'H:i, m月d日,Y';
        } elseif($language === 'en') {
            $value -= 46800;
            $format = 'm/d/Y H:i';
        }

        return date($format, $value);
    }
}

if (!function_exists('base58_encode')) {
    
    /**
     * BASE58编码
     * @access protected
     * @return String
     */
    function base58_encode($string)
    {
        $alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $base = strlen($alphabet);
        if (is_string($string) === false) {
            return false;
        }
        if (strlen($string) === 0) {
            return '';
        }
        $bytes = array_values(unpack('C*', $string));
        $decimal = $bytes[0];
        for ($i = 1, $l = count($bytes); $i < $l; $i++) {
            $decimal = bcmul($decimal, 256);
            $decimal = bcadd($decimal, $bytes[$i]);
        }
        $output = '';
        while ($decimal >= $base) {
            $div = bcdiv($decimal, $base, 0);
            $mod = bcmod($decimal, $base);
            $output .= $alphabet[$mod];
            $decimal = $div;
        }
        if ($decimal > 0) {
            $output .= $alphabet[$decimal];
        }
        $output = strrev($output);
        foreach ($bytes as $byte) {
            if ($byte === 0) {
                $output = $alphabet[0] . $output;
                continue;
            }
            break;
        }
        return (string) $output;
    }
}

if (!function_exists('loadCache')) {
    /**
     * 返回相应的缓存
     *
     * @param string $name
     * @param function $fallback
     * @return void
     */
    function loadCache(string $name, $fallback, $force = false) {
        static $cache = [];

        if (!isset($cache["sys.{$name}"]) || $force) {
            $data = redis()->get("sys.{$name}");

            if (!$data || $force) {
                $data = $fallback($name);
                redis()->set("sys.{$name}", $data, 86400);

                $cache["sys.{$name}"] = $data;
            }
        }

        return $cache["sys.{$name}"];
    }
}
