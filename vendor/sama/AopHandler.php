<?php
namespace sama;

/**
 * 用于aop实现
 */
class AopHandler {

	private $target;

	public function __construct($target) {
		$this->target = $target;
	}

	public function __call($method, $arg) {
		$before = 'before';
		$result = $this->target->$method(...$arg);
		$after = 'after';
		return $result;
	}

	public function __get($name) {
		dd("get");
		$before = 'before';
		dd($before);
		$result = $this->target->$name;
		dd($result);
		$after = 'after';
		return $result;
	}

	public function __set($name, $value) {
		dd("set");
		$before = 'before';
		dd($before);
		$this->target->$name = $value;
		dd($result);
		$after = 'after';
		return;
	}
}