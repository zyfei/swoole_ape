<?php
namespace sama\tag;

use samaSama;
use sama\Sama;

/**
 * 类拦截器
 */
class HttpControllerTag {

	private $class_url = null;

	private $methods = array(
		'get',
		'post',
		'head',
		'options',
		'put',
		'patch',
		'delete',
		'trace',
		'connect'
	);

	/**
	 * 类注释解析
	 */
	public function httpController($cla, $parm = "") {
		if ($parm == "") {
			$parm = "/";
			$parm_arr = explode("\\", $cla);
			foreach ($parm_arr as $k => $n) {
				if ($n == "") {
					unset($parm_arr[$k]);
					continue;
				}
				for ($i = 0; $i < strlen($n); $i ++) {
					$char = ord($n[$i]);
					if ($char > 64 && $char < 91 && $i != 0) {
						$parm = $parm . "_";
					}
					$parm = $parm . strtolower($n[$i]);
				}
				$parm = $parm . "/";
			}
			$parm = substr($parm, 0, strlen($parm) - 1);
		}
		$this->class_url = $parm;
		
		// 这个时候把类和方法绑定在http路由上
		$obj = Sama::getBean($cla);
		$ref = new \ReflectionClass($cla);
		$methods = $ref->getMethods();
		foreach ($methods as $k => $n) {
			$url = $parm . "/" . $n->name;
			Sama::addHttpRoute($cla, $n->name, $url);
		}
	}

	/**
	 * 路由后半部分
	 */
	public function mapping($cla, $method, $parm = null) {
		if ($parm == "") {
			return;
		}
		// 清除自动添加的方法路由
		$new_url = $this->class_url . "/" . $parm;
		Sama::updateHttpRoute($cla, $method, $new_url);
	}

	/**
	 * 限制访问方法
	 */
	public function method($cla, $method, $parm = null) {
		if ($parm == "") {
			return;
		}
		$methods = $parm_arr = explode("|", $parm);
		foreach ($methods as $k => $n) {
			if ($n == "") {
				unset($methods[$k]);
			}
		}
		// 清除自动添加的方法路由
		$filter = array();
		$filter["method"] = $methods;
		Sama::updateHttpRoute($cla, $method, null, $filter);
	}
}