<?php

# 用户名密码登录
$router->post('user/login', ['uses' => 'UserController@login', 'middleware' => ['api.init']]);
# 快速登录
$router->post('user/fastLogin', ['uses' => 'UserController@fastLogin', 'middleware' => ['api.init', 'api.checklogin', 'api.checkforbid']]);
# 注册
$router->post('user/register', ['uses' => 'UserController@register', 'middleware' => ['api.init']]);

$router->post('user/resetCache', ['uses' => 'UserController@resetCache', 'middleware' => ['api.init']]);
$router->post('user/live', ['uses' => 'UserController@live', 'middleware' => ['api.init', 'api.checklogin']]);
$router->post('user/modify', ['uses' => 'UserController@moidfy', 'middleware' => ['api.init']]);

# 取用户信息
$router->post('user/getUserInfo', ['uses' => 'UserController@getUserInfo', 'middleware' => ['api.init']]);
# 取自己的用户信息
$router->post('user/getMyUserInfo', ['uses' => 'UserController@getMyUserInfo', 'middleware' => ['api.init', 'api.checklogin', 'api.checkforbid']]);
# 取批量用户信息
$router->post('user/getMultiUserInfo', ['uses' => 'UserController@getMultiUserInfo', 'middleware' => ['api.init']]);
# 编辑用户
$router->post('user/edit', ['uses' => 'UserController@edit', 'middleware' => ['api.init', 'api.checklogin']]);
# 后台编辑用户
$router->post('user/modify', ['uses' => 'UserController@modify', 'middleware' => ['api.init', 'api.checkadmin']]);
# 获取验证码
$router->post('user/getCode', ['uses' => 'UserController@getCode', 'middleware' => ['api.init', 'api.checklogin']]);
# 绑定手机号
$router->post('user/bind', ['uses' => 'UserController@bind', 'middleware' => ['api.init', 'api.checklogin']]);
# 解绑手机号
$router->post('user/unbind', ['uses' => 'UserController@unbind', 'middleware' => ['api.init', 'api.checklogin']]);
# 绑定状态
$router->post('user/getBinds', ['uses' => 'UserController@getBinds', 'middleware' => ['api.init', 'api.checklogin']]);
$router->post('user/getUserBinds', ['uses' => 'UserController@getUserBinds', 'middleware' => ['api.init']]);
# 重置密码
$router->post('user/reset', ['uses' => 'UserController@reset', 'middleware' => ['api.init', 'api.checklogin']]);
# 获取找回密码验证码
$router->post('user/getForgotCode', ['uses' => 'UserController@getForgotCode', 'middleware' => ['api.init']]);
# 找回密码
$router->post('user/forgot', ['uses' => 'UserController@forgot', 'middleware' => ['api.init']]);
# enter
$router->post('user/customer', ['uses' => 'UserController@enter', 'middleware' => ['api.init', 'api.checklogin', 'api.checkforbid']]);

# 我关注的人列表
$router->post('follow/getFollowings', ['uses' => 'FollowController@getFollowings', 'middleware' => ['api.init', 'api.checklogin']]);
# 我的粉丝列表
$router->post('follow/getFollowers', ['uses' => 'FollowController@getFollowers', 'middleware' => ['api.init', 'api.checklogin']]);
# 用户关注的人列表
$router->post('follow/getUserFollowings', ['uses' => 'FollowController@getUserFollowings', 'middleware' => ['api.init', 'api.checklogin']]);
# 用户的粉丝列表
$router->post('follow/getUserFollowers', ['uses' => 'FollowController@getUserFollowers', 'middleware' => ['api.init', 'api.checklogin']]);

# 关注
$router->post('follow/add', ['uses' => 'FollowController@add', 'middleware' => ['api.init', 'api.checklogin']]);
# 批量关注
$router->post('follow/multiAdd', ['uses' => 'FollowController@multiAdd', 'middleware' => ['api.init', 'api.checklogin']]);
# 取消关注
$router->post('follow/cancel', ['uses' => 'FollowController@cancel', 'middleware' => ['api.init', 'api.checklogin']]);
# 是否关注
$router->post('follow/isFollowed', ['uses' => 'FollowController@isFollowed', 'middleware' => ['api.init', 'api.checklogin']]);
# 是否好友
$router->post('follow/isFriend', ['uses' => 'FollowController@isFriend', 'middleware' => ['api.init', 'api.checklogin']]);
# 好友列表
$router->post('follow/getFriends', ['uses' => 'FollowController@getFriends', 'middleware' => ['api.init', 'api.checklogin']]);
$router->post('follow/setOptionNotice', ['uses' => 'FollowController@setOptionNotice', 'middleware' => ['api.init', 'api.checklogin']]);

# 拉黑
$router->post('blocked/add', ['uses' => 'BlockedController@add', 'middleware' => ['api.init', 'api.checklogin']]);
# 取消拉黑
$router->post('blocked/cancel', ['uses' => 'BlockedController@cancel', 'middleware' => ['api.init', 'api.checklogin']]);
# 是否被拉黑
$router->post('blocked/isblocked', ['uses' => 'BlockedController@isblocked', 'middleware' => ['api.init', 'api.checklogin']]);
# 被我拉黑的uids
$router->post('blocked/getbids', ['uses' => 'BlockedController@getBids', 'middleware' => ['api.init', 'api.checklogin']]);
# 被我拉黑的用户列表
$router->post('blocked/getblocked', ['uses' => 'BlockedController@getBlocked', 'middleware' => ['api.init', 'api.checklogin']]);

# 封禁
$router->post('forbidden/forbidden', ['uses' => 'ForbiddenController@forbidden', 'middleware' => ['api.init', 'api.checkadmin']]);
# 取消封禁
$router->post('forbidden/unforbidden', ['uses' => 'ForbiddenController@unForbidden', 'middleware' => ['api.init', 'api.checkadmin']]);
# 是否被禁言(单个)
$router->post('forbidden/isforbidden', ['uses' => 'ForbiddenController@isForbidden', 'middleware' => ['api.init', 'api.checklogin']]);
# 是否被禁言(多个)
$router->post('forbidden/isforbiddenUsers', ['uses' => 'ForbiddenController@isForbiddenUsers', 'middleware' => ['api.init', 'api.checklogin']]);
# 超管
$router->post('superpatroller/reset', ['uses' => 'SuperPatrollerController@reset', 'middleware' => ['api.init', 'api.checkadmin']]);

# 用户vip信息
$router->post('vip/getVipInfo', ['uses' => 'VipController@getVipInfo', 'middleware' => ['api.init', 'api.checklogin']]);
# 用户等级信息
$router->post('user/getExpInfo', ['uses' => 'ExpController@getExpInfo', 'middleware' => ['api.init', 'api.checklogin']]);
# 设定vip
$router->post('user/setVip', ['uses' => 'VipController@setVip', 'middleware' => ['api.init', 'api.checkadmin']]);

# 封禁设备
$router->post('forbidden/forbidden_device', ['uses' => 'ForbiddenDeviceController@forbidden', 'middleware' => ['api.init', 'api.checkadmin']]);
# 取消封禁设备
$router->post('forbidden/unforbidden_device', ['uses' => 'ForbiddenDeviceController@unForbidden', 'middleware' => ['api.init', 'api.checkadmin']]);
