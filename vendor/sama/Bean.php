<?php
namespace sama;

use sama\aop\AopHandler;

/**
 * new: 先完善tag系统
 * new: 在此基础上添加apo功能，bean工厂返回的将不是
 */
class Bean {

	private static $bean = null;

	// 存储注册的回调函数，为键值对关联数组
	protected $bindings = array();

	// 存储类=>bean 的键值对
	protected $_bindings = array();

	/**
	 * 判断是否绑定过
	 */
	public function _has($name) {
		if (isset($this->bindings[$name])) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 通过类名获取bean名字
	 */
	public function _getBeanNameByClass($cla) {
		if (isset($this->_bindings[$cla])) {
			return $this->_bindings[$cla];
		} else {
			return false;
		}
	}

	// 注册函数，最开始需要调用本函数绑定接口和对应回调函数到$bindings
	public function _bind($name, $concrete = null) {
		// 如果不是匿名函数，那么就转换成匿名函数
		if (! $concrete instanceof \Closure) {
			$this->_bindings[$concrete] = $name;
			$concrete = function ($c) use ($name, $concrete) {
				$method = ($name == $concrete) ? 'build' : 'get';
				return $c->$method($concrete);
			};
		}
		if (! isset($this->bindings[$name])) {
			unset($this->bindings[$name]);
		}
		$this->bindings[$name] = $concrete;
	}

	/**
	 * 实例化bean对象
	 */
	public function _get($object) {
		// 如果没bind过，那么尝试通过名字构建
		if (! isset($this->bindings[$object])) {
			return $this->build($object);
		}
		// 如果是已经实例化，那么直接返回
		if ((! $this->bindings[$object] instanceof \Closure) && is_object($this->bindings[$object])) {
			return $this->bindings[$object];
		}
		// 构建
		$obj = $this->build($this->bindings[$object]);
		$aop_heandler = new AopHandler($object, $obj);
		$this->bindings[$object] = $aop_heandler;
		return $aop_heandler;
	}

	// 实例化对象，如果是回调函数，直接调用其进行实例化
	// 如果不是则尝试通过反射实例化
	protected function build($concrete) {
		if ($concrete instanceof \Closure) {
			return $concrete($this);
		}
		// 生成反射类
		$reflector = new \ReflectionClass($concrete);
		// 判断是否可以实例化
		if (! $reflector->isInstantiable()) {
			echo $message = "Target ($concrete) is not instantiable \n";
			return NULL;
		}
		// 获取构造函数
		$constructor = $reflector->getConstructor();
		if (is_null($constructor)) {
			return new $concrete();
		}
		// 获取构造函数需要的参数
		$dependencies = $constructor->getParameters();
		// 生成参数，同样是调用了get来生成
		$instances = $this->getDependencies($dependencies);
		// 生成实例化对象，并返回
		$obj = $reflector->newInstanceArgs($instances);
		return $obj;
	}

	// 根据array(ReflectionParameter)来获取构造函数需要的参数数组
	protected function getDependencies($parameters) {
		$dependencies = [];
		foreach ($parameters as $parameter) {
			// 获取参数的类名，除了自定义类对象，均会返回NULL
			$dependency = $parameter->getClass();
			if (is_null($dependency)) {
				$dependencies[] = NULL;
			} else {
				// 自动生成对象参数
				$dependencies[] = $this->resolveClass($parameter);
			}
		}
		return (array) $dependencies;
	}

	protected function resolveClass(ReflectionParameter $parameter) {
		// 调用get生成对象
		return $this->get($parameter->getClass()->name);
	}

	public static function __callStatic($method, $arg) {
		if (static::$bean == null) {
			static::$bean = new Bean();
		}
		return call_user_func_array(array(
			static::$bean,
			$method
		), $arg);
	}

	public function __call($method, $arg) {
		$method = "_" . $method;
		return call_user_func_array(array(
			$this,
			$method
		), $arg);
	}
}