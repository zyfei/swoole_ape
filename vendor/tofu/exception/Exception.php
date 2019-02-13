<?php
namespace tofu\exception;

/**
 * 简单的自定义异常类
 */
class Exception extends \Exception {

	public function __construct($message, $code = 0) {
		parent::__construct($message, $code);
	}

	public function __toString() {
		// 重写父类方法，自定义字符串输出的样式
		$file = $this->getFile();
		$line = $this->getLine();
		$code = $this->getCode();
		$exception = $this->getMessage();
		
		$data = [
			'msg' => $exception,
			'file' => $file,
			'line' => $line,
			'code' => $code
		];
		// dd(json_encode($data));
		return parent::__toString();
	}
}


