<?php
namespace sama;

use sama\Sama;

/**
 * 路由
 */
class Route {
	
	
	public static function route($app) {
		// 获取处理类和方法
		$cm_i = strrpos($app->url, "/");
		$controller_url = substr($app->url, 0, $cm_i);
		if ($controller_url == "") {
			$controller_url = "/";
		}
		$method = substr($app->url, $cm_i + 1);
		if (key_exists($controller_url, AC::$controller_url_map)) {
			$app->controller = Ac::$controller_url_map[$controller_url];
			if (key_exists(Ac::$controller_url_map[$controller_url], Ac::$controller_methods_honey_map)) {
				if (key_exists($method, Ac::$controller_methods_honey_map[Ac::$controller_url_map[$controller_url]])) {
					$method = Ac::$controller_methods_honey_map[Ac::$controller_url_map[$controller_url]][$method];
				}
			}
			$app->method = $method;
		}
	}
	
}