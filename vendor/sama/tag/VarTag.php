<?php
namespace sama\tag;

use sama\Ioc;

/**
 * 属性标签
 */
class VarTag {

	/**
	 * 自动装配 Bean
	 */
	public function bean($cla, $name) {
		Ioc::bind($name, $cla);
	}
}