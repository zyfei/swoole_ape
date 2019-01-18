<?php
namespace ape;

use ape\db\MysqlPool;
use ape\tag\ViewTag;

/**
 * 综合各类请求信息
 */
class App {

	public $request = null;

	public $response = null;

	public $send_msg = '';

	// 访问的host
	public $home = '';

	// mysql连接
	public $mysql = null;

	// 1 http
	public $type = 0;

	// 协程id
	public $co_uid = 0;

	// 处理这个请求的路由类详情
	public $route_map = null;

	/**
	 * http类型请求初始化
	 */
	public function setHttp($request, $response) {
		$this->request = $request;
		$protocol = (! empty($request->server['https']) && $request->server['https'] !== 'off' || $request->server['server_port'] == 443) ? "https://" : "http://";
		$request->server["home"] = $protocol . $request->header['host'];
		$request->server["request_url"] = $protocol . $request->header['host'] . $request->server['request_uri'];
		$this->response = $response;
		$this->type = 1;
		$this->co_uid = \Co::getuid();
	}

	/**
	 * 发送
	 */
	public function send($str = "") {
		$this->send_msg = $this->send_msg . $str;
	}

	/**
	 * 结束发送，如果分段，请使用response->write
	 *
	 * @param string $str        	
	 */
	public function end($str = "") {
		$this->send_msg = $this->send_msg . $str;
		$this->response->end($str);
	}

	/**
	 * 取参数
	 */
	public function input($name, $defaule = "") {
		if (array_key_exists($name, $this->request->get)) {
			if ($this->request->get[$name] !== "") {
				$default = $this->request->get[$name];
			}
		} elseif (array_key_exists($name, $this->request->post)) {
			if ($this->request->post[$name] !== "") {
				$default = $this->request->post[$name];
			}
		}
	}

	public function get_mysql() {
		if ($this->mysql == null) {
			$this->mysql = MysqlPool::getInstance()->get_connection();
		}
		return $this->mysql;
	}

	/**
	 * 回收资源
	 */
	public function free_mysql() {
		// 如果这个连接分配了mysql
		if ($this->mysql != null) {
			MysqlPool::getInstance()->free_connection($this->mysql);
		}
	}

	public function view($tmp, $arr) {
		$view = \Ape::getBean("ape.view.view");
		$view_tmp_dir = "";
		if (key_exists($this->route_map['cla'], ViewTag::$view_cla_tmpdir_map)) {
			$view_tmp_dir = RUN_DIR . DIRECTORY_SEPARATOR . ViewTag::$view_cla_tmpdir_map[$this->route_map['cla']].DIRECTORY_SEPARATOR;
		}
		return $view->view($this, $view_tmp_dir, $tmp, $arr);
	}
}