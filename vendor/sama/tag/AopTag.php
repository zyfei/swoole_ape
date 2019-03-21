<?php
namespace sama\tag;

use sama\Bean;
use sama\aop\AopHandler;
use sama\AC;

/**
 * aop自定义标签类
 */
class AopTag {

	/**
	 * 自动装配 Bean
	 */
	public static function aop($cla, $name = null) {
		if ($name == null) {
			$name = $cla;
		}
		Bean::bind($name, $cla);
		$aop = Bean::get($name);
		// 处理方法
		if ($aop->isset("pointMethod")) {
			$pointMethod = $aop->pointMethod;
			if (key_exists("include", $pointMethod) && is_array($pointMethod["include"])) {
				foreach ($pointMethod["include"] as $k => $n) {
					AopHandler::$pointIncludeMethods[$n][$name] = 1;
				}
			}
			if (key_exists("exclude", $pointMethod) && is_array($pointMethod["exclude"])) {
				foreach ($pointMethod["exclude"] as $k => $n) {
					AopHandler::$pointExcludeMethods[$n][$name] = 1;
				}
			}
		}
		
		// 处理bean
		if ($aop->isset("pointBean")) {
			$pointBean = $aop->pointBean;
			if (key_exists("include", $pointBean) && is_array($pointBean["include"])) {
				foreach ($pointBean["include"] as $k => $n) {
					$ref = new \ReflectionClass(Bean::get($n)->get_target());
					$methods = $ref->getMethods();
					foreach ($methods as $k2 => $n2) {
						$method = $n2->name;
						AopHandler::$pointIncludeMethods[$n . "::" . $method][$name] = 1;
					}
				}
			}
			if (key_exists("exclude", $pointBean) && is_array($pointBean["exclude"])) {
				foreach ($pointBean["exclude"] as $k => $n) {
					$ref = new \ReflectionClass(Bean::get($n)->get_target());
					$methods = $ref->getMethods();
					foreach ($methods as $k2 => $n2) {
						$method = $n2->name;
						AopHandler::$pointExcludeMethods[$n . "::" . $method][$name] = 1;
					}
				}
			}
		}
		
		// 会在所有注解解析之后最后执行
		AC::final_run_exe(function () use ($aop, $name) {
			// 处理注解
			if ($aop->isset("pointAnnotation")) {
				$pointAnnotation = $aop->pointAnnotation;
				if (key_exists("include", $pointAnnotation) && is_array($pointAnnotation["include"])) {
					foreach ($pointAnnotation["include"] as $k => $n) {
						if (key_exists($n, AC::$classTags)) {
							$tags = AC::$classTags[$n];
							foreach ($tags as $tags2) {
								foreach ($tags2 as $tag) {
									$ref = new \ReflectionClass($tag[1][0]);
									$methods = $ref->getMethods();
									foreach ($methods as $k2 => $n2) {
										$method = $n2->name;
										AopHandler::$pointIncludeMethods[$tag[1][0] . "::" . $method][$name] = 1;
									}
								}
							}
						}
						if (key_exists($n, AC::$methodTags)) {
							$tags = AC::$methodTags[$n];
							foreach ($tags as $tags2) {
								foreach ($tags2 as $tag) {
									AopHandler::$pointIncludeMethods[$tag[1][0] . "::" . $tag[1][1]][$name] = 1;
								}
							}
						}
					}
				}
				
				if (key_exists("exclude", $pointAnnotation) && is_array($pointAnnotation["exclude"])) {
					foreach ($pointAnnotation["exclude"] as $k => $n) {
						if (key_exists($n, AC::$classTags)) {
							$tags = AC::$classTags[$n];
							foreach ($tags as $tags2) {
								foreach ($tags2 as $tag) {
									$ref = new \ReflectionClass($tag[1][0]);
									$methods = $ref->getMethods();
									foreach ($methods as $k2 => $n2) {
										$method = $n2->name;
										AopHandler::$pointExcludeMethods[$tag[1][0] . "::" . $method][$name] = 1;
									}
								}
							}
						}
						if (key_exists($n, AC::$methodTags)) {
							$tags = AC::$methodTags[$n];
							foreach ($tags as $tags2) {
								foreach ($tags2 as $tag) {
									AopHandler::$pointExcludeMethods[$tag[1][0] . "::" . $tag[1][1]][$name] = 1;
								}
							}
						}
					}
				}
			}
			
			// 最后去重
			foreach (AopHandler::$pointExcludeMethods as $k=>$n){
				if(key_exists($k, AopHandler::$pointIncludeMethods)){
					unset(AopHandler::$pointIncludeMethods[$k]);
				}
			}
		});
	}
}