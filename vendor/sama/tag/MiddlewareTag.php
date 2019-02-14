<?php
namespace sama\tag;

use samaSama;
use sama\Sama;
use sama\util\SamaBeanFactory;

/**
 * 类拦截器
 */
class MiddlewareTag {

	/**
	 * 类注释解析
	 */
	public function middleware($cla, $parm = "") {
		if ($parm == "") {
			return;
		}
		$methods = $parm_arr = explode("|", $parm);
		foreach ($methods as $k => $n) {
			if ($n == "") {
				unset($methods[$k]);
			}
		}
		SamaBeanFactory::add_class_middleware($cla, $methods);
	}
}