<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// | 管理中心路由
// +----------------------------------------------------------------------
// | @author: k
// | @version: 2.1.1
// | @date: 2020/09/26
// +----------------------------------------------------------------------
use think\facade\Route;

// 其它接口
Route::group('driver', function() {
    // 测试
    Route::get('test', 'auth/test');
    // 获取返回一枚可交互的TOKEN值
    Route::get('auth/access_token', 'auth/buildGenerateToken');
    // 无身份校验
    Route::group(function() {
        // 鉴权
        Route::post('auth/login', 'auth/login');
    })->middleware('Console');
    // 无权限校验
    Route::group(function() {
        Route::get('auth/userinfo', 'auth/getInfo')->name('userinfo');
        Route::group('hkstock', function() {
            // 数据接口、切换数据接口
            Route::rule('channel', 'hongkong/channel', 'POST|GET')->name('hk_channel');
        })->prefix('driver.stock.');
    })->middleware('Console', false);
    // 有权限校验
    Route::group(function() {
        Route::post('auth/register', 'auth/addUser')->name('register');
        Route::group('hkstock', function() {
            // 获取代码
            Route::get('search', 'hongkong/search')->name('hk_sk');
            // 最新行情数据
            Route::get('quotes/:code', 'hongkong/quotes')->name('hk_sq');
            // 大盘信息
            Route::get('market', 'hongkong/market')->name('hk_mk');
            // 货币汇率
            Route::get('foreign', 'hongkong/foreign')->name('hk_sc');
        })->prefix('driver.stock.');
    })->middleware('Console', true);
})->prefix('driver.');