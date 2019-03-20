<?php
namespace sama\db;

use sama\Sama;
use sama\exception\Exception;

/**
 * mysql连接池
 */
class MysqlPool extends AbstractPool {

	// 连接池配置
	private $config = null;

	// 连接池实例
	private static $_instance = null;

	/**
	 * 初始化
	 */
	private static $hasInstance = false;

	public static function getInstance() {
		// 防止协程并行创建多个实例
		if (! self::$hasInstance) {
			if (key_exists("database", Sama::$_config)) {
				self::$hasInstance = true;
				self::$_instance = new MysqlPool();
				self::$_instance->config = Sama::$_config["database"];
				self::$_instance->init(Sama::$_config["database"]);
			} else {
				throw new Exception("database config is null");
			}
		}
		return self::$_instance;
	}

	protected function createDb() {
		$db = new \Swoole\Coroutine\Mysql();
		$db->connect($this->config);
		if ($db->connected == false) {
			dd("createDb error : " . $db->connect_error);
			return false;
		}
		return $db;
	}

	protected function checkDb($db) {
		return $db->connected;
	}
}