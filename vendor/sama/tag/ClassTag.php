<?php
namespace sama\tag;

use sama\Ioc;
use sama\AC;

/**
 * 视图根目录
 */
class ClassTag {

	/**
	 * 自动装配 Bean
	 */
	public static function bean($cla, $name = null) {
		if ($name == null) {
			$name = $cla;
		}
		Ioc::bind($name, $cla);
	}

	/**
	 * 控制器
	 */
	public static function controller($cla, $parm = "") {
		Ioc::bind($cla, $cla);
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
		AC::$controller_url_map[$parm] = $cla;
	}

	/**
	 * 类注释解析
	 */
	public static function view($cla, $parm = "") {
		AC::$view_cla_tmpdir_map[$cla] = $parm;
	}
}