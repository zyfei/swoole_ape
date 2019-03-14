<?php
namespace http\filter;

/**
 * 切片类
 * @aop()
 */
class TestAop {

	// 定义bean切入点 - 这个bean类里的方法执行都会经过此切面类的代理
	public $pointMethod = array(
		// 定义需要切入的bean
		"include" => array(
			"\\http\\Index::bbb"
		),
		// 排除的bean
		"exclude" => array()
	);

	// 定义bean切入点 - 这个bean类里的方法执行都会经过此切面类的代理
	public $pointBean = array(
		// 定义需要切入的bean
		"include" => array(),
		// 排除的bean
		"exclude" => array()
	);

	// 定义注解切入点 - 所有包含使用了对应注解的方法都会经过此切面类的代理
	public $pointAnnotation = array(
		// 定义需要切入
		"include" => array(),
		// 排除的
		"exclude" => array()
	);

	/**
	 * 标记方法为前置通知 - 在目标方法执行前先执行此方法
	 */
	function before() {
		var_dump(' before1 ');
	}

	/**
	 * 标记方法为后置通知 - 在目标方法执行后执行此方法
	 */
	public function after() {
		var_dump(' after1 ');
	}

	/**
	 * 标记方法为最终返回通知
	 */
	public function afterReturn(JoinPoint $joinPoint) {
		$result = $joinPoint->getReturn();
		return $result . ' afterReturn1 ';
	}

	/**
	 * 标记方法为环绕通知 - 在目标方法执行前、后都执行此方法
	 */
	public function around(ProceedingJoinPoint $proceedingJoinPoint) {
		$this->test .= ' around-before1 ';
		$result = $proceedingJoinPoint->proceed();
		$this->test .= ' around-after1 ';
		return $result . $this->test;
	}

	/**
	 * 标记方法为异常通知 - 在目标方法执行抛出异常时执行此方法
	 */
	public function afterThrowing() {
		echo "aop=1 afterThrowing !\n";
	}
}