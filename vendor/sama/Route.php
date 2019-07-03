<?php
namespace sama;

use sama\Sama;

/**
 * 路由
 */
class Route {

	public static function route($app, $uri) {
		// 获取处理类和方法
		$cm_i = strrpos($uri, "/");
		$controller_url = substr($uri, 0, $cm_i);
		if ($controller_url == "") {
			$controller_url = "/";
		}
		$method = substr($uri, $cm_i + 1);
		if (key_exists($controller_url, AC::$controller_url_map)) {
			$app->controller = Ac::$controller_url_map[$controller_url];
			if (key_exists(Ac::$controller_url_map[$controller_url], Ac::$controller_methods_honey_map)) {
				if (key_exists($method, Ac::$controller_methods_honey_map[Ac::$controller_url_map[$controller_url]])) {
					$method = Ac::$controller_methods_honey_map[Ac::$controller_url_map[$controller_url]][$method];
				}
			}
			$app->method = $method;
		}
		if ($app->controller == null || $app->method == null) {
			$app->end();
			return;
		}
		$obj = Bean::get($app->controller);
		$method = $app->method;
		$obj_return = $obj->$method($app);
		if (is_array($obj_return) || is_object($obj_return)) {
			$obj_return = json_encode($obj_return);
		}
		$app->return_data = $app->return_data . $obj_return;
		$app->end();
	}
}