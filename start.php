<?php
// 将PHP提供的stream、sleep、pdo、mysqli、redis等功能从同步阻塞切换为协程的异步IO
\Swoole\Runtime::enableCoroutine();

// 根目录
define('RUN_DIR', __DIR__);
// 引入自动加载类
require_once '_lib/Autoloader.php';
require_once '_lib/helper.php';

/**
 * 这个config是全局配置。默认配置在_lib下的config.php中
 */
$config = array(
	"worker_num" => 2
);
$config["database"] = array(
	'host' => '127.0.0.1',
	'port' => 3306,
	'user' => 'root',
	'password' => 'root',
	'database' => 'pingan_act_shop',
	'charset' => 'utf8',
	'timeout' => 10,
	'min' => 2, // 数据库连接池最少连接
	'max' => 5, // 数据库连接池最多连接
	'spareTime' => 60 // 每60秒检查一次
);

// 设置配置
Ape::config($config);

Ape::listen("http://0.0.0.0:18080",array("name"=>"aa"));
// 开启服务
Ape::listen("http://0.0.0.0:18081",array("name"=>"bb"));
//Ape::listen("udp://0.0.0.0:18082");
Ape::runAll();
// 后面不用写代码，不运行
