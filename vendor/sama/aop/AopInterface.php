<?php
namespace sama\aop;

use sama\App;

/**
 * 用于aop实现
 */
interface AopInterface {

	/**
	 * 标记方法为前置通知 - 在目标方法执行前先执行此方法
	 */
	public function before(App $app);

	/**
	 * 标记方法为后置通知 - 在目标方法执行后执行此方法
	 */
	public function after(App $app);

	/**
	 * 标记方法为环绕通知 - 在目标方法执行前、后都执行此方法
	 */
	public function around(App $app);

	/**
	 * 标记方法为异常通知 - 在目标方法执行抛出异常时执行此方法
	 */
	public function throwing(App $app);
}