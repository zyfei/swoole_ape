<?php
namespace sama\process;

use sama\App;
use sama\Sama;
use sama\Bean;

class Worker {

	/**
	 * http消息
	 */
	public static function onRequest($app) {
		App::route($app);
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

	/**
	 * 连接触发
	 */
	public static function onConnect($server, $fd, $reactor_id) {
		dd("onConnect");
	}

	/**
	 * tcp消息
	 */
	public static function onReceive($server, $fd, $reactor_id, $data) {
		dd("onReceive");
	}

	/**
	 * websocket消息
	 */
	public static function onMessage($server, $frame) {
		echo "onMessage \n";
	}

	/**
	 * websocket消息
	 */
	public static function onPacket($server, $data, $addr) {
		echo "onPacket \n";
	}
}