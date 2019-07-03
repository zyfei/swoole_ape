<?php
namespace sama\process;

use sama\App;
use sama\Sama;
use sama\Bean;
use sama\Route;

class Worker {

	/**
	 * http消息
	 */
	public static function onRequest($app) {
		Route::route($app,$app->uri);
	}

	/**
	 * 连接触发
	 */
	public static function onConnect($server, $fd, $reactor_id) {
		//dd("onConnect");
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
	public static function onMessage($app) {
		Route::route($app,$app->url);
	}

	/**
	 * websocket消息
	 */
	public static function onPacket($server, $data, $addr) {
		echo "onPacket \n";
	}
}