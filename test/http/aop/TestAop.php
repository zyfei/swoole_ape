<?php
namespace test\http\aop;

use sama\aop\AopInterface;
use sama\App;
use test\http\Index;
use test\http\Index2;

/**
 * 切片类
 * @aop
 */
class TestAop implements AopInterface {

	// 定义bean切入点 - 这个bean类里的方法执行都会经过此切面类的代理
	public $pointMethod = array(
		// 定义需要切入的bean
		"include" => array(
			Index::class . "::bbb"
		),
		// 排除的bean
		"exclude" => array(
			Index2::class . "::aaa",
		)
	);

	// 定义bean切入点 - 这个bean类里的方法执行都会经过此切面类的代理
	public $pointBean = array(
		// 定义需要切入的bean
		"include" => array(
			Index::class
		),
		// 排除的bean
		"exclude" => array()
	);

	// 定义注解切入点 - 所有包含使用了对应注解的方法都会经过此切面类的代理
	public $pointAnnotation = array(
		// 定义需要切入
		"include" => array(
			"view"
		),
		// 排除的
		"exclude" => array()
	);

	/**
	 * 标记方法为前置通知 - 在目标方法执行前先执行此方法
	 */
	public function before(App $app) {
		//dd("aop before");
	}

	/**
	 * 标记方法为后置通知 - 在目标方法执行后执行此方法
	 */
	public function after(App $app) {
	}

	/**
	 * 标记方法为环绕通知 - 在目标方法执行前、后都执行此方法
	 */
	public function around(App $app) {
	}

	/**
	 * 标记方法为异常通知 - 在目标方法执行抛出异常时执行此方法
	 */
	public function throwing(App $app) {
	}
}