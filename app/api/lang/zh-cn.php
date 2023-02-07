<?php
/*
 * 语言包(简体中文).
 * @Date: 2021-05-10 20:19:31
 */
return [
    'invalid token'                     => '请求错误',
    'no logged in'                      => '未登录，请登录后再操作',
    'logged online'                     => '账户已登录',
    'no access'                         => '无访问权限',
    'no exist'                          => '记录不存在',
    'exist record'                      => '已存在的记录',
    'has children'                      => '存在子记录',
    'user attr avatar'                  => '头像',
    'user attr realname'                => '呢称',
    'user attr email'                   => '邮箱',
    'user attr mobile'                  => '手机',

    'introduction.platform'             => '控制管理成员(Platform.User) 系统内部控制台操作人员',
    'introduction.generate'             => '控制管理成员(Generate.User) 通用内容管理人员',
    'introduction.platform.super'       => '超级管理员(Super.Administrator) 是系统自动生成的第一个用户,具有最高权限,可以拥有更改系统应用的权限。',
    'introduction.platform.admin'       => '系统管理员(Admin.MASTER) 是控制系统顶级主要用户，具有内容管理的主要权限。',

    'rule.parent_id'                    => '所属上级',
    'rule.name'                         => '权限标识',
    'rule.title'                        => '权限名称',
    'rule.title.create'                 => '添加权限',
    'rule.title.update'                 => '编辑权限',
    'restore.form.create'               => '创建备份',
    'restore.fail'                      => '创建备份失败，请返回重试',

    'role.name'                         => '角色名称',
    'role.remark'                       => '备注说明',
    'role.roles'                        => '授予权限',
    'role.title.create'                 => '添加角色',
    'role.title.update'                 => '编辑角色',

    'group.title'                       => '用户组名',
    'group.roles'                       => '应用角色',
    'group.range'                       => '应用范围',
    'group.remark'                      => '备注说明',
    'group.title.create'                => '添加组',
    'group.title.update'                => '编辑组',
    'group.platform'                    => '内置账户',

    'user.form.create'                  => '添加用户',
    'user.form.update'                  => '编辑用户',
    'user.validate.no_group'            => '用户组不存在',
    'user.form.username'                => '登录账户',
    'user.form.password'                => '登录密码',
    'user.form.group'                   => '所属组',
    'user.form.realname'                => '用户呢称',
    'user.form.avatar'                  => '头像上传',
    'user.form.mobile'                  => '联系电话',
    'user.form.email'                   => '电子邮箱',
    'user.delete.fail'                  => '删除用户失败',

    'category.form.create'              => '添加分类',
    'category.form.update'              => '编辑分类',
    'category.del.has_children'         => '无法删除存在子类的分类',
    'category_banner.form.create'       => '新增分类图片',
    'category_banner.form.update'       => '编辑分类图片',
    'category_label.form.create'        => '新增分类标签',
    'category_label.form.update'        => '编辑分类标签',

    'banner.form.create'                => '新增轮播图',
    'banner.form.update'                => '编辑轮播图',

    'contact.form.create'               => '添加联系方式',
    'contact.form.update'               => '编辑联系方式',

    'label.form.create'                 => '新建标签',
    'label.form.update'                 => '编辑标签',

    'product.form.create'               => '添加产品',
    'product.form.update'               => '编辑产品',
    'product.list.search'               => '符合%s产品有%s条',

    'article.form.create'               => '添加文章',
    'article.form.update'               => '编辑文章',
    'article.list.search'               => '符合%s文章有%s条',
    'article_banner.form.create'        => '新增文章横幅',
    'article_banner.form.update'        => '编辑文章横幅',

    'link.form.create'                  => '新增链接',
    'link.form.update'                  => '编辑链接',
    'feedback.form.update'              => '留言明细',

    'config.app'                        => '系统运行',
    'config.app.show_error_msg'         => '开启调试',

    'config.admin'                      => '后台参数',
    'config.admin.title'                => '后台界面标题',
    'config.admin.user_logged_mode'     => '独占登录模式',
    'config.admin.user_logged_calibration' => '登录检测间隔',
    'config.admin.user_logged_stay'     => '会话保存时间',
    'config.admin.logo'                 => '后台标志图片',
    'config.admin.limit'                => '记录分页',
    'config.admin.user'                 => '登录配置',
    'config.admin.backup'               => '备份设置',
    'config.admin.backup_path'          => '备份目录',
    'config.admin.backup_part'          => '分卷大小',
    'config.admin.upload_path'          => '上传目录',
    'config.admin.flowcard'             => '流量卡',
    'config.admin.flowcard_account'     => '登录账户',
    'config.admin.flowcard_amount'      => '当前余额',

    'config.common'                     => '全局配置',
    'config.common.status'              => '应用服务状态',
    'config.common.page'                => '记录显示页码',

    'config.common.wechat'              => '微信公众号',
    'config.common.wechat_appId'        => 'AppId',
    'config.common.wechat_token'        => 'Token',
    'config.common.wechat_encodingaes'  => '编码值',
    'config.common.wechat_secret'       => '密钥',
    'config.common.wechat_template_expire' => '到期模板消息',

    'config.common.room_release'        => '房源发布',
    'config.common.room_release_condi'  => '租出房屋后发布',
    'config.common.room_release_allowed'=> '允许发布房源数',

    'config.common.alicloud'            => '阿里云',
    'config.common.alicloud_appId'      => 'appId',
    'config.common.alicloud_secret'     => '密钥',

    'config.common.luat'                => 'LUAT流量卡',
    'config.common.luat_uri'            => '网关',
    'config.common.luat_appId'          => 'appId',
    'config.common.luat_secret'         => '密钥',
    'config.common.luat_account'        => '账户',
    'config.common.luat_balance'        => '余额',

    'config.home'                       => '前端显示',
    'config.home.title'                 => '应用标题',
    'config.home.keywords'              => 'SEO关键字',
    'config.home.description'           => '描述信息',
    'config.home.phone'                 => '24小时联系电话',
    'config.home.siteName'              => '应用名称',
    'config.home.email'                 => '联系邮箱',
    'config.home.siteUrl'               => '访问地址',
    'config.home.logo'                  => 'LOGO标志',

    'logs.user.create'                  => '用户创建',
    'logs.user.delete'                  => '用户开始',

    'wechat_menus.form.create'          => '新增菜单',
    'wechat_menus.form.update'          => '编辑菜单',

    'sys_merchant.form.create'          => '添加商户',
    'sys_merchant.form.update'          => '编辑商户',
];
