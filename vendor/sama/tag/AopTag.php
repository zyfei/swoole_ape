<?php
namespace sama\tag;

use sama\Ioc;
use sama\AC;

/**
 * aop自定义标签类
 */
class AopTag {

	/**
	 * 自动装配 Bean
	 */
	public static function aop($cla, $name = null) {
		dd("-aop继续做---------------------------".$cla);
		if ($name == null) {
			$name = $cla;
		}
		Ioc::bind($name, $cla);
	}
	
}