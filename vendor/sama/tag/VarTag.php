<?php
namespace sama\tag;

use sama\Bean;
use sama\exception\Exception;

/**
 * 属性标签
 */
class VarTag {

	/**
	 * 自动装配 Bean
	 */
	public static function resource($cla, $var_name, $bean_name) {
		$cla_bean_name = Bean::getBeanNameByClass($cla);
		if ($cla_bean_name === false) {
			throw new Exception('sama\tag\VarTag::resource : ' . $cla . " not bean.");
		}
		if (! Bean::has($cla_bean_name)) {
			throw new Exception('sama\tag\VarTag::resource : ' . $cla . " bean non-existent.");
		}
		if (! Bean::has($bean_name)) {
			throw new Exception('sama\tag\VarTag::resource : ' . $bean_name . " bean non-existent.");
		}
		$obj = Bean::get($cla_bean_name);
		$obj->$var_name = Bean::get($bean_name);
	}
}