<?php
namespace ape\tag;

/**
 * 视图根目录
 */
class ViewTag {

	// key 类
	// value view根目录
	public static $view_cla_tmpdir_map = array();

	/**
	 * 类注释解析
	 */
	public function view($cla, $parm = "") {
		self::$view_cla_tmpdir_map[$cla] = $parm;
		return true;
	}
}