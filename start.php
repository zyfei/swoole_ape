<?php
use sama\Sama;

// 引入自动加载类
require_once 'vendor/Autoloader.php';
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
Sama::config($config);

Sama::listen("http://0.0.0.0:18080");
Sama::listen("http://0.0.0.0:18081");
Sama::runAll();
// 后面不用写代码，不运行
