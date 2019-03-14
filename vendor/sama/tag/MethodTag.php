<?php
namespace sama\tag;

use sama\AC;

/**
 * 方法标签
 */
class MethodTag {

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
	 * 限制访问方法
	 */
	public static function mapping($cla, $method, $url = null) {
		if ($url == "") {
			return;
		}
		$fc = substr($url, 0, 1);
		if ($fc == "/") {
			$url = substr($url, 1);
		}
		AC::$controller_methods_honey_map[$cla][$url] = $method;
	}
}