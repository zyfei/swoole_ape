<?php
namespace sama\util;

/**
 * 读取php文件，并且初始化bean
 */
class SamaBeanFactory {

	/**
	 * 所有的bean
	 */
	private static $beans = array();

	/**
	 * 类注释标签
	 */
	private static $class_tags = array();

	/**
	 * http路由
	 */
	public static $http_route_maps = array();

	public static $_http_route_method_url_maps = array();

	/**
	 * 类=>类的拦截器
	 */
	public static $_middleware_maps = array();

	/**
	 * 定义了标签的类
	 */
	private static $_need_load_class_arr = array();

	/**
	 * 运行
	 */
	public static function run() {
		self::loadPhpFiles();
		self::create_beans();
		self::create_tags();
	}

	/**
	 * 读取所有的php文件
	 */
	private static function loadPhpFiles() {
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
				// dd($className);
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
				self::$_need_load_class_arr[] = "\\" . str_replace(DIRECTORY_SEPARATOR, "\\", $className);
			}
		}
	}

	private static function create_beans() {
		// 创建bean
		foreach (self::$_need_load_class_arr as $obj) {
			$ref = new \ReflectionClass($obj);
			$class_tag = $ref->getDocComment();
			$class_tag = preg_replace('# #', '', $class_tag);
			$k2 = "bean";
			$preg = '/\@' . $k2 . '\([\s\S]*?\)/';
			preg_match_all($preg, $class_tag, $class_tag_arr);
			$class_tag_arr = $class_tag_arr[0];
			if (count($class_tag_arr) <= 0) {
				$preg = '/\@' . $k2 . '[\s\S]*?/';
				preg_match_all($preg, $class_tag, $class_tag_arr2);
				$class_tag_arr2 = $class_tag_arr2[0];
				if (count($class_tag_arr2) > 0) {
					$class_tag_arr[] = "@" . $k2 . "()";
				}
			}
			foreach ($class_tag_arr as $n) {
				$rn = 0;
				$pram = str_replace("@" . $k2 . "(", "", $n, $rn);
				if ($rn == 0) {
					$pram = null;
				} else {
					$pram = substr($pram, 0, strlen($pram) - 1);
				}
				// 已经匹配到了，进入Taq类里面执行操作
				// self::getBean("__need_load_class_arr_" . $k2)->$k2($obj, $pram);
				if ($pram == "") {
					$pram = $obj;
				}
				self::addBean($pram, $obj);
			}
		}
	}

	/**
	 * 获取所有标签声明
	 */
	private static function create_tags() {
		$tags = require_once 'tags.php';
		$tag_methods = array();
		foreach ($tags as $k => $n) {
			$bean_name = "__need_load_class_arr_" . $k;
			self::addBean($bean_name, $n);
			$class2 = new \ReflectionClass($n);
			$methods2 = $class2->getMethods();
			$class_tag = array();
			foreach ($methods2 as $k2 => $n2) {
				$class_tag[] = $n2->name;
			}
			$tag_methods[$k] = $class_tag;
		}
		self::$class_tags = $tag_methods;
		foreach (self::$_need_load_class_arr as $n) {
			self::loadClassTag($n);
		}
	}

	/**
	 * 读取并解析类的注释。不读取静态方法
	 */
	private static function loadClassTag($obj) {
		$ref = new \ReflectionClass($obj);
		$class_tag = $ref->getDocComment();
		$class_tag = preg_replace('# #', '', $class_tag);
		foreach (self::$class_tags as $k2 => $n2) {
			$preg = '/\@' . $k2 . '\([\s\S]*?\)/';
			preg_match_all($preg, $class_tag, $class_tag_arr);
			$class_tag_arr = $class_tag_arr[0];
			if (count($class_tag_arr) <= 0) {
				$preg = '/\@' . $k2 . '[\s\S]*?/';
				preg_match_all($preg, $class_tag, $class_tag_arr2);
				$class_tag_arr2 = $class_tag_arr2[0];
				if (count($class_tag_arr2) > 0) {
					$class_tag_arr[] = "@" . $k2 . "()";
				}
			}
			foreach ($class_tag_arr as $n) {
				if (! self::hasBean($obj)) {
					self::addBean($obj, $obj);
				}
				$rn = 0;
				$pram = str_replace("@" . $k2 . "(", "", $n, $rn);
				if ($rn == 0) {
					$pram = null;
				} else {
					$pram = substr($pram, 0, strlen($pram) - 1);
				}
				// 已经匹配到了，进入Taq类里面执行操作
				self::getBean("__need_load_class_arr_" . $k2)->$k2($obj, $pram);
				// 接下来解析方法
				self::loadMethodTag($k2, $obj);
			}
		}
	}

	/**
	 * 加载方法注释
	 * 将符合规定的注释返回
	 */
	private static function loadMethodTag($_need_load_class_arr_tag, $class_tag) {
		$ref = new \ReflectionClass(self::getBean($class_tag));
		$methods = $ref->getMethods();
		if ($methods) {
			foreach ($methods as $method) {
				$tag = $method->getDocComment();
				$tag = preg_replace('# #', '', $tag);
				foreach (self::$class_tags[$_need_load_class_arr_tag] as $k2 => $n2) {
					$preg = '/\@' . $n2 . '\([\s\S]*?\)/';
					preg_match_all($preg, $tag, $method_tag_arr);
					$method_tag_arr = $method_tag_arr[0];
					if (count($method_tag_arr) <= 0) {
						$preg = '/\@' . $n2 . '[\s\S]*?/';
						preg_match_all($preg, $tag, $method_tag_arr2);
						$method_tag_arr2 = $method_tag_arr2[0];
						if (count($method_tag_arr2) > 0) {
							$method_tag_arr[] = "@" . $n2 . "()";
						}
					}
					
					foreach ($method_tag_arr as $n) {
						$rn = 0;
						$pram = str_replace("@" . $n2 . "(", "", $n, $rn);
						if ($rn == 0) {
							$pram = null;
						} else {
							$pram = substr($pram, 0, strlen($pram) - 1);
						}
						// 已经匹配到了，进入Taq类里面执行操作
						self::getBean("__need_load_class_arr_" . $_need_load_class_arr_tag)->$n2($class_tag, $method->name, $pram);
					}
				}
			}
		}
	}

	/**
	 * 获取定义的bean
	 * 支持注释和手动set两组方式
	 */
	public static function getBean($bean_name) {
		if (! key_exists($bean_name, self::$beans)) {
			dd("beanname:$bean_name don't  exist！");
			return false;
		}
		return self::$beans[$bean_name];
	}

	/**
	 * 判断是否有bin
	 */
	public static function hasBean($bean_name) {
		if (! key_exists($bean_name, self::$beans)) {
			return false;
		}
		return true;
	}

	/**
	 * 设置bean
	 *
	 * @param $cla 类        	
	 * @param $construct_parms 构造参数        	
	 */
	public static function addBean($bean_name, $cla, $args = null) {
		if (! class_exists($cla)) {
			dd($cla . " don't exist");
			return false;
		}
		if (key_exists($bean_name, self::$beans)) {
			dd("beanname:$bean_name exist！");
			return false;
		}
		
		$c = (new \ReflectionClass($cla))->newInstanceWithoutConstructor();
		if (method_exists($c, "__construct")) {
			if ($args != null) {
				call_user_func_array(array(
					&$c,
					"__construct"
				), $args);
			} else {
				call_user_func(array(
					&$c,
					"__construct"
				));
			}
		}
		self::$beans[$bean_name] = $c;
		return $c;
	}

	/**
	 * 添加http路由
	 */
	public static function addHttpRoute($cla, $method, $url, $filter = array()) {
		while (1) {
			$url = str_replace('//', '/', $url, $s_c);
			if ($s_c <= 0) {
				break;
			}
		}
		$route = array();
		$route["cla"] = $cla;
		$route["method"] = $method;
		$route["filter"] = $filter;
		self::$http_route_maps[$url] = $route;
		self::$_http_route_method_url_maps[$cla . '\\' . $method] = $url;
	}

	public static function updateHttpRoute($cla, $method, $new_url = null, $filter = null) {
		$old_url = self::$_http_route_method_url_maps[$cla . '\\' . $method];
		
		$route = self::$http_route_maps[$old_url];
		if ($filter != null) {
			foreach ($filter as $k => $n) {
				$route["filter"][$k] = $n;
			}
		}
		
		// 如果url不变的话
		if ($new_url == null) {
			self::$http_route_maps[$old_url] = $route;
		} else {
			while (1) {
				$new_url = str_replace('//', '/', $new_url, $s_c);
				if ($s_c <= 0) {
					break;
				}
			}
			unset(self::$http_route_maps[$old_url]);
			self::$_http_route_method_url_maps[$cla . '\\' . $method] = $new_url;
			self::$http_route_maps[$new_url] = $route;
		}
	}

	/**
	 * 添加类拦截器
	 */
	public static function add_class_middleware($cla, $middlewares) {
		self::$_middleware_maps[$cla] = $middlewares;
	}
}