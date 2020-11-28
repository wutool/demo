<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
});

$router->get('ping', function () {
    return 'pong';
});

// app配置
$router->post('/config/gets', ['uses' => 'ConfigController@getConfig', 'middleware' => ['api.init']]);
$router->post('/config/set', ['uses' => 'ConfigController@set', 'middleware' => ['api.init', 'api.checkadmin']]);
$router->post('/config/del', ['uses' => 'ConfigController@del', 'middleware' => ['api.init', 'api.checkadmin']]);

$router->post('/web/uploadImage', ['uses' => 'UploadController@uploadImage', 'middleware' => ['api.init']]);

// 调试消息
$router->post('/dryrun/messagesend', ['uses' => 'DryrunController@messageSend']);
