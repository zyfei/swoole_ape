<?php
namespace sama;

use sama\Sama;

/**
 * 存储中转数据
 * AC 交流电
 * DC 直流电
 */
class AC {

	/**
	 * url和controller的对应关系
	 */
	public static $controller_url_map = array();

	/**
	 * 记录着方法的优先级别名
	 * 需要使用方法的时候，会优先读取别名
	 * 别名=>真名
	 */
	public static $controller_methods_honey_map = array();

	// key 类
	// value view根目录
	public static $view_cla_tmpdir_map = array();

	// 所有的标记类
	public static $classTags = array();

	public static $methodTags = array();

	// 注解和对应类，注解和对应方法的对象关系
	
	// 最后执行的方法
	private static $final_run_exe_arr = array();

	/**
	 * 读取所有的php文件
	 */
	public static function run() {
		$dir_iterator = new \RecursiveDirectoryIterator(RUN_DIR);
		$iterator = new \RecursiveIteratorIterator($dir_iterator);
		foreach ($iterator as $file) {
			// only check php files
			if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
				continue;
			}
			$namespace = str_replace(RUN_DIR . DIRECTORY_SEPARATOR, "", $file->getPath());
			$namespace = str_replace(\Autoloader::$_vendorPath . DIRECTORY_SEPARATOR, "", $namespace);
			$file_name = (string) $file;
			$contents = file_get_contents($file_name);
			$preg = '/class [\s\S]*? /';
			preg_match_all($preg, $contents, $res);
			foreach ($res[0] as $k => $n) {
				$className = trim(get_between($n, "class ", ' '));
				$className = $namespace . DIRECTORY_SEPARATOR . $className;
				// 判断是否是类并加载
				if ($k == 0) {
					if (! \Autoloader::loadByNamespace($className)) {
						break;
					}
				} else {
					// 判断是否存在class
					if (! class_exists("\\" . str_replace(DIRECTORY_SEPARATOR, "\\", $className), false)) {
						break;
					}
				}
				// 在这里开始循环处理tag
				$cla = str_replace(DIRECTORY_SEPARATOR, "\\", $className);
				// 先处理
				self::loadClassTag($cla);
			}
		}
		
		// 真正处理标签
		for ($level = 1; $level <= 3; $level ++) {
			foreach (self::$classTags as $tags) {
				if (key_exists("level_" . $level, $tags)) {
					foreach ($tags["level_" . $level] as $n) {
						call_user_func_array($n[0], $n[1]);
					}
				}
			}
			foreach (self::$methodTags as $tags) {
				if (key_exists("level_" . $level, $tags)) {
					foreach ($tags["level_" . $level] as $n) {
						call_user_func_array($n[0], $n[1]);
					}
				}
			}
		}
		
		// 执行final方法
		foreach (self::$final_run_exe_arr as $n) {
			call_user_func_array($n[0], $n[1]);
		}
	}

	/**
	 * 读取并解析类的注释。不读取静态方法
	 */
	private static function loadClassTag($obj) {
		$ref = new \ReflectionClass($obj);
		// 处理类标签
		$class_tag_arr = self::preg_match_tag($ref->getDocComment());
		foreach ($class_tag_arr as $n) {
			$rn = 0;
			$tag = get_between($n, "@", "(");
			$pram = str_replace("@" . $tag . "(", "", $n, $rn);
			$args = array(
				$obj
			);
			if ($rn != 0) {
				$pram = substr($pram, 0, strlen($pram) - 1);
				$args = array_merge($args, explode(",", $pram));
			}
			// 判断不同的注解优先级
			if (key_exists($tag, Sama::$_tag["class"])) {
				self::$classTags[$tag]["level_" . Sama::$_tag["class"][$tag]["level"]][] = array(
					Sama::$_tag["class"][$tag]["value"], // 处理注解的方法
					$args // 参数
				);
			}
		}
		
		// 处理方法标签
		$methods = $ref->getMethods();
		if ($methods) {
			foreach ($methods as $method) {
				$method_tag_arr = self::preg_match_tag($method->getDocComment());
				foreach ($method_tag_arr as $n) {
					$rn = 0;
					$tag = get_between($n, "@", "(");
					$pram = str_replace("@" . $tag . "(", "", $n, $rn);
					$args = array(
						$obj,
						$method->name
					);
					if ($rn != 0) {
						$pram = substr($pram, 0, strlen($pram) - 1);
						$args = array_merge($args, explode(",", $pram));
					}
					if (key_exists($tag, Sama::$_tag["method"])) {
						self::$methodTags[$tag]["level_" . Sama::$_tag["method"][$tag]["level"]][] = array(
							Sama::$_tag["method"][$tag]["value"],
							$args
						);
					}
				}
			}
		}
	}

	/**
	 * 解析符合标准的注解
	 */
	private static function preg_match_tag($str) {
		$str = preg_replace('# #', '', $str);
		// foreach (self::$class_tags as $k2 => $n2) {
		$preg = '/\@[\s\S]*?\\([\s\S]*?\)/';
		preg_match_all($preg, $str, $tag_arr);
		$tag_arr = $tag_arr[0];
		foreach ($tag_arr as $n) {
			$str = str_replace($n, "", $str);
		}
		$preg = '/\@[^\s]+\s?/';
		preg_match_all($preg, $str, $class_tag_arr2);
		$class_tag_arr2 = $class_tag_arr2[0];
		foreach ($class_tag_arr2 as $n) {
			$n = str_replace("\n", "", $n);
			$n = str_replace("\r", "", $n);
			$tag_arr[] = $n . "()";
		}
		return $tag_arr;
	}

	/**
	 * 所有注解加载完成之后执行
	 */
	public static function final_run_exe($fun, $args = array()) {
		self::$final_run_exe_arr[] = array(
			$fun,
			$args
		);
	}
}