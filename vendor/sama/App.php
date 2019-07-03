<?php
namespace sama;

use sama\db\MysqlPool;
use sama\view\View;

/**
 * 综合各类请求信息
 */
class App {

	/**
	 * 协议类型
	 */
	public const HTTP_TYPE = 1;

	public const WEBSOCKET_TYPE = 2;

	public $uri = null;

	public $url = null;

	// 当前协议
	public $protocol = null;

	public $fd = null;

	public $server = null;

	public $request = null;

	public $response = null;

	// 访问的host
	public $home = '';

	// mysql连接
	public $mysql = null;

	// 1 http 2websocket
	public $type = 0;

	// 协程id
	public $co_uid = 0;

	// 处理这个请求的类
	public $controller = null;

	// 处理这个请求的方法
	public $method = null;

	// 请求包体
	public $data = null;

	// 返回包体
	public $return_data = "";

	/**
	 * http类型请求初始化
	 * type默认是1.但是websocket onOpen也使用这个
	 */
	public function setHttp($request, $response) {
		$this->request = $request;
		$protocol = (! empty($request->server['https']) && $request->server['https'] !== 'off' || $request->server['server_port'] == 443) ? "https://" : "http://";
		$request->server["home"] = $protocol . $request->header['host'];
		$request->server["request_url"] = $protocol . $request->header['host'] . $request->server['request_uri'];
		$this->response = $response;
		$this->type = App::HTTP_TYPE;
		$this->co_uid = \Co::getuid();
		if($request->post==null){
			$request->post = array();
		}
		if($request->get==null){
			$request->get= array();
		}
		$this->data = array_merge($request->post, $request->get);
		$this->uri = $request->server['request_uri'];
		$this->url = $request->server["request_url"];
		$this->fd = $request->fd;
	}

	/**
	 * websocket类型请求初始化
	 */
	public function setWebsocket($server, $request, $open = false) {
		$this->request = $request;
		// 如果是初始化
		if ($open) {
			$protocol = (! empty($request->server['https']) && $request->server['https'] !== 'off' || $request->server['server_port'] == 443) ? "https://" : "http://";
			$request->server["home"] = $protocol . $request->header['host'];
			$request->server["request_url"] = $protocol . $request->header['host'] . $request->server['request_uri'];
			$this->uri = $request->server['request_uri'];
			$this->url = $request->server["request_url"];
			$this->data = $request->get;
			$this->key= "onOpen";
		} else {
			$this->key = $request->data["key"];
			$this->url = $request->data["url"];
			$this->data = $request->data["data"];
		}
		$this->fd = $request->fd;
		$this->server = $server;
		$this->type = App::WEBSOCKET_TYPE;
		$this->co_uid = \Co::getuid();
	}

	public function close() {
		switch ($this->type) {
			case APP::WEBSOCKET_TYPE:
				$this->server->close();
				break;
			default:
		}
	}

	/**
	 * 发送
	 * 在http模式下fd无效
	 */
	public function send($data = "", $fd = null) {
		switch ($this->type) {
			case APP::HTTP_TYPE:
				$this->return_data = $this->return_data . $data;
				break;
			case APP::WEBSOCKET_TYPE:
				if ($fd == null) {
					$fd = $this->fd;
				}
				$arr["key"] = $this->key;
				$arr["msg"] = $data;
				$arr["code"] = 200;
				$this->server->push($fd, json_encode($arr));
				break;
			default:
		}
	}

	/**
	 * 结束发送，如果分段，请使用response->write
	 *
	 * @param string $str        	
	 */
	public function end($data = "", $fd = null) {
		$this->return_data = $this->return_data . $data;
		switch ($this->type) {
			case APP::HTTP_TYPE:
				$this->response->end($this->return_data);
				break;
			case APP::WEBSOCKET_TYPE:
				{
					if ($fd == null) {
						$fd = $this->fd;
					}
					if ($this->return_data !== "") {
						$arr["key"] = $this->key;
						$arr["msg"] = $this->return_data;
						$arr["code"] = 200;
						$this->server->push($fd, json_encode($arr));
					}
				}
				break;
			default:
				$this->return_data = "";
		}
	}

	public function api($msg, $code = 200, $fd = null) {
		$arr["msg"] = numeric_to_string($msg);
		$arr["code"] = $code;
		switch ($this->type) {
			case APP::HTTP_TYPE:
				{
					$this->response->header('content-type', 'application/json');
					$this->return_data = $this->return_data . json_encode($arr);
				}
				break;
			case APP::WEBSOCKET_TYPE:
				{
					if ($fd == null) {
						$fd = $this->fd;
					}
					$arr["key"] = $this->key;
					$this->server->push($this->fd, json_encode($arr));
				}
				break;
			default:
		}
	}

	/**
	 * 取参数
	 */
	public function input($name, $default = "") {
		if (array_key_exists($name, $this->data)) {
			if ($this->data[$name] !== "") {
				$default = $this->data[$name];
			}
		}
		return $default;
	}

	public function get_db() {
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
		$view = Bean::get(View::class);
		$view_tmp_dir = "";
		if (key_exists($this->controller, AC::$view_cla_tmpdir_map)) {
			$view_tmp_dir = RUN_DIR . DIRECTORY_SEPARATOR . AC::$view_cla_tmpdir_map[$this->controller] . DIRECTORY_SEPARATOR;
		}
		return $view->view($this, $view_tmp_dir, $tmp, $arr);
	}

	/**
	 * 获取此协程使用的app
	 */
	public static function getApp() {
		return Sama::get_co_poll(\Co::getuid());
	}
}