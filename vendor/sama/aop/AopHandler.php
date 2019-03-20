<?php
namespace sama\aop;

use sama\Ioc;
use sama\exception\Exception;
use sama\Sama;

/**
 * 用于aop实现
 */
class AopHandler {

	/**
	 * 方法=>array(拦截器1=>1，拦截器2=>1)
	 */
	public static $pointIncludeMethods = array();

	public static $pointExcludeMethods = array();

	public static $pointMethods = array();

	private $target;

	private $bean_name;

	public function __construct($bean_name, $target) {
		$this->bean_name = $bean_name;
		$this->target = $target;
	}

	public function __call($method, $arg) {
		$have_aop = 0;
		$have_exception = 0;
		if (key_exists($this->bean_name . "::" . $method, AopHandler::$pointIncludeMethods)) {
			$have_aop = 1;
			foreach (AopHandler::$pointIncludeMethods[$this->bean_name . "::" . $method] as $k => $n) {
				$aop = Ioc::get($k);
				if ($aop->method_exists("before")) {
					$aop->before(Sama::getApp());
				}
				if ($aop->method_exists("around")) {
					$aop->around(Sama::getApp());
				}
			}
		}
		$result = null;
		try {
			if ($this->method_exists($method)) {
				$result = $this->target->$method(...$arg);
			} else {
				throw new Exception("Call to undefined method " . $method);
			}
		} catch (Exception $e) {
			$have_exception = 1;
			dd($e->getMessage());
		}
		if ($have_aop == 1) {
			foreach (AopHandler::$pointIncludeMethods[$this->bean_name . "::" . $method] as $k => $n) {
				$aop = Ioc::get($k);
				if ($have_exception == 1 && $aop->method_exists("throwing")) {
					$aop->throwing(Sama::getApp());
				}
				if ($aop->method_exists("after")) {
					$aop->after(Sama::getApp());
				}
				if ($aop->method_exists("around")) {
					$aop->around(Sama::getApp());
				}
			}
		}
		return $result;
	}

	public function __get($name) {
		$result = $this->target->$name;
		return $result;
	}

	public function __set($name, $value) {
		$this->target->$name = $value;
		return;
	}

	public function isset($name = null) {
		if ($name == null) {
			return false;
		}
		return isset($this->target->$name);
	}

	public function method_exists($name = null) {
		if ($name == null) {
			return false;
		}
		return method_exists($this->target, $name);
	}

	public function get_target() {
		return $this->target;
	}
}