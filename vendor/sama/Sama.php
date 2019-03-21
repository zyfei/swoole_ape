<?php
namespace sama;

use sama\App;
use sama\view\View;
use sama\exception\Exception;
use sama\AC;
use sama\process\Worker;
use sama\process\Task;

/**
 * 高性能框架
 */
class Sama {

	/**
	 * 全局配置
	 */
	public static $_config = null;

	/**
	 * 标签列表
	 */
	public static $_tag = null;

	/**
	 * 启动文件位置
	 *
	 * @var string
	 */
	private static $_startFile = "";

	/**
	 * 当前server实例
	 */
	public static $server = null;

	/**
	 * 初始化时候的worker
	 */
	private static $_init_workers = array();

	/**
	 * 使用status命令时候数据
	 */
	public static $_globalStatistics = array();

	public static $_statisticsFile = null;

	/**
	 * 进程通道,manager进程使用
	 */
	public static $_workers_channel = null;

	/**
	 * 记录所有进程状态,manager进程使用
	 */
	public static $workers = array();

	public static $dead_workers = array();

	/**
	 * 协程信息数组，保存协程id=>app格式的关系
	 */
	private static $coroutine_app_map = array();

	private static $_maxSocketNameLength;

	/**
	 * 输出了什么
	 */
	private static $safeEchoData = "";

	/**
	 * 配置协议stream类型
	 */
	protected static $_builtinTransports = array(
		'http' => SWOOLE_SOCK_TCP,
		'tcp' => SWOOLE_SOCK_TCP,
		'ws' => SWOOLE_SOCK_TCP,
		'udp' => SWOOLE_SOCK_UDP
	);

	/**
	 * 不同类型的配置不同
	 */
	protected static $_builtinConfigs = array(
		'http' => array(
			"open_http_protocol" => true,
			"open_websocket_protocol" => true,
			"tcp_fastopen" => true
		),
		'tcp' => array(
			"open_http_protocol" => false,
			"open_websocket_protocol" => false,
			"tcp_fastopen" => false
		),
		'ws' => array(
			"open_http_protocol" => true,
			"open_websocket_protocol" => true,
			"tcp_fastopen" => false
		),
		'udp' => array(
			"open_http_protocol" => false,
			"open_websocket_protocol" => false,
			"tcp_fastopen" => false
		)
	);

	/**
	 * 传入新配置，覆盖默认配置
	 */
	public static function config($config = array()) {
		// 引入标准配置文件
		if (self::$_config == null) {
			require_once 'config/helper.php';
			self::$_config = require_once 'config/config.php';
			self::$_tag = require_once 'config/tag.php';
		}
		// 使用新配置
		foreach ($config as $k2 => $n2) {
			self::$_config[$k2] = $n2;
		}
	}

	/**
	 * 开始监听,此方法传入的配置只在此listen生效
	 */
	public static function listen($address = null, $config = array()) {
		// 引入标准配置文件
		if (self::$_config == null) {
			require_once 'config/helper.php';
			self::$_config = require_once 'config/config.php';
			self::$_tag = require_once 'config/tag.php';
		}
		if ($address === null) {
			throw new Exception('address is null');
		}
		$address_info = parse_url($address);
		if (! isset($address_info['scheme'])) {
			throw new Exception('scheme is null');
		}
		if (! isset($address_info['port'])) {
			$address_info['port'] = 80;
		}
		if (! key_exists($address_info['scheme'], self::$_builtinTransports)) {
			throw new Exception('not support ' . $address_info['scheme']);
		}
		// 添加到worker中去
		$worker = array();
		$worker["address"] = $address;
		$worker["scheme"] = $address_info['scheme'];
		$worker["port"] = $address_info['port'];
		$worker["host"] = $address_info['host'];
		$worker["config"] = $config;
		$worker["count"] = key_exists("worker_num", $config) ? $config["worker_num"] : self::$_config["worker_num"];
		
		// Get maximum length of socket name.
		$socket_name_length = strlen($address);
		if (self::$_maxSocketNameLength < $socket_name_length) {
			self::$_maxSocketNameLength = $socket_name_length;
		}
		self::$_init_workers[] = $worker;
	}

	/**
	 * 管理进程启动
	 */
	private static function managerStart($server) {
		self::$_statisticsFile = sys_get_temp_dir() . '/swooleSama' . $server->master_pid . '.status';
		swoole_set_process_name('swoole_sama: manager process  start_file=' . self::$_startFile);
		// 等待数据
		sleep(1);

		// 每10秒处理一次数据
		function exec_statisticsFile() {
			while (Sama::$_workers_channel->stats()["queue_num"] > 0) {
				$a = Sama::$_workers_channel->pop();
				if ($a["m"] == "worker") {
					$worker = $a["data"];
					Sama::$workers[$worker['pid']] = $worker;
				}
			}
			foreach (Sama::$workers as $k => $n) {
				// 如果不存活了
				if (! \swoole_process::kill($k, 0)) {
					Sama::$dead_workers[$k] = $n;
					unset(Sama::$workers[$k]);
				}
			}
			Sama::$_globalStatistics["workers"] = Sama::$workers;
			Sama::$_globalStatistics["dead_workers"] = Sama::$dead_workers;
			Sama::$_globalStatistics["request_count"] = Sama::$server->stats()["request_count"];
			file_put_contents(Sama::$_statisticsFile, json_encode(Sama::$_globalStatistics));
		}
		exec_statisticsFile();
		swoole_timer_tick(3000, function () {
			exec_statisticsFile();
		});
	}

	/**
	 * 工作进程和任务进程启动
	 */
	private static function workerStart($server, $worker_id) {
		//下面这些文件不会reload
		//var_dump(get_included_files());
		AC::run();
		self::$server = $server;
		global $argv;
		if ($server->taskworker) {
			swoole_set_process_name('swoole_sama: task_worker process  ' . $server->setting["name"] . ' ' . $server->setting["address"]);
		} else {
			swoole_set_process_name('swoole_sama: worker process  ' . $server->setting["name"] . ' ' . $server->setting["address"]);
		}
		go(function () use ($server) {
			function worker_channel($server) {
				$data = array();
				$data["is_taskworker"] = $server->taskworker;
				$data["worker_id"] = $server->worker_id;
				$data["pid"] = $server->worker_pid;
				$data["memory"] = round(memory_get_usage(true) / (1024 * 1024), 2) . "M";
				$data["status"] = $server->stats();
				// 通知manager进程(注意不是master进程)
				Sama::send_worker_channel("worker", $data);
			}
			worker_channel($server);
			// 3秒刷新一次
			swoole_timer_tick(3000, function () use ($server) {
				worker_channel($server);
			});
		});
	}
	
	/**
	 * 消息来之前绑定协程=>app等
	 */
	private static function before_message($app){
		// 添加协程关系
		self::$coroutine_app_map[\Co::getuid()] = $app;
		// 升级到最新版终于支持defer了
		defer(function () use ($app) {
			// 在这里回收mysql资源以及一些其他操作
			$app->free_mysql();
			unset(self::$coroutine_app_map[\Co::getuid()]);
			unset($app);
		});
	}
	
	/**
	 * 开始web服务
	 */
	public static function run() {
		if (count(self::$_init_workers) <= 0) {
			throw new Exception('address is null');
		}
		foreach (self::$_init_workers as $k => $n) {
			if ($k == 0) {
				self::$server = new \swoole_server($n["host"], $n["port"], self::$_config["server_mode"], self::$_builtinTransports[$n["scheme"]]);
				$config = self::$_config;
				foreach (self::$_builtinConfigs[$n["scheme"]] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				foreach ($n["config"] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				$config["address"] = $n["address"];
				self::$server->set($config);
			} else {
				$config = self::$_builtinConfigs[$n["scheme"]];
				foreach ($n["config"] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				$config["address"] = $n["address"];
				$s2 = self::$server->addListener($n["host"], $n["port"], self::$_builtinTransports[$n["scheme"]])->set($config);
			}
		}
		
		self::$server->on('managerStart', function ($server) {
			self::managerStart($server);
		});
		self::$server->on('workerStart', function ($server, $worker_id) {
			self::workerStart($server, $worker_id);
		});
		
		self::$server->on('connect', function ($server, $fd, $reactor_id) {
			Worker::onConnect($server, $fd, $reactor_id);
		});
		self::$server->on('receive', function ($server, $fd, $reactor_id, $data) {
			Worker::onReceive($server, $fd, $reactor_id,$data);
		});
		
		/**
		 * http请求
		 */
		self::$server->on('request', function ($request, $response) {
			go(function () use ($request,$response) {
				$app = new App();
				$app->setHttp($request, $response);
				self::before_message($app);
				Worker::onRequest($app);
			});
		});
		
		/**
		 * WebSocket请求触发
		 */
		self::$server->on('message', function ($server, $frame) {
			Worker::onMessage($server, $frame);
		});
		
		/**
		 * 接收到UDP数据包时回调此函数
		 */
		self::$server->on('packet', function ($server, $data, $addr) {
			Worker::onPacket($server, $data, $addr);
		});
		
		
		self::$server->on('task', function ($server, $task_id, $reactor_id, $data) {
			Task::onTask($server, $task_id, $reactor_id, $data);
		});
		self::$server->on('finish', function ($server, $task_id, $data) {
			Task::onTask($server, $task_id, $reactor_id, $data);
		});
		self::$server->start();
	}

	public static function runAll() {
		self::init();
		self::parseCommand();
		self::displayUI();
		self::run();
	}

	private static function init() {
		// 清空缓存
		self::clear_storage();
		// Start file.
		$backtrace = debug_backtrace();
		self::$_startFile = $backtrace[count($backtrace) - 1]['file'];
		swoole_set_process_name('swoole_sama: master process  start_file=' . self::$_startFile);
		// 创建队列,最大1024个容量
		self::$_workers_channel = new \Swoole\Channel(1024);
		self::$workers = array();
		self::$_globalStatistics['name'] = self::$_config["name"];
		self::$_globalStatistics['start_time'] = time();
		self::$_globalStatistics['reactor_num'] = self::$_config["reactor_num"];
		self::$_globalStatistics['worker_num'] = self::$_config["worker_num"];
		self::$_globalStatistics['listen'] = self::$_init_workers;
		self::$_globalStatistics['maxSocketNameLength'] = self::$_maxSocketNameLength;
	}

	/**
	 * Parse command.
	 * php yourfile.php start | stop | restart | reload | status
	 *
	 * @return void https://wiki.swoole.com/wiki/page/p-server/reload.html
	 */
	protected static function parseCommand() {
		global $argv;
		// Check argv;
		$start_file = $argv[0];
		if (! isset($argv[1])) {
			exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
		}
		
		// Get command.
		$command = trim($argv[1]);
		$command2 = isset($argv[2]) ? $argv[2] : '';
		
		// Start command.
		$mode = '';
		if ($command === 'start') {
			if ($command2 === '-d') {
				// 开启守护进程
				self::$_config["daemonize"] = 1;
				$mode = 'in DAEMON mode';
			} else {
				$mode = 'in DEBUG mode';
			}
		}
		self::log("SwooleSama[$start_file] $command $mode");
		
		$master_pid = @file_get_contents(self::$_config["pid_file"]);
		$master_is_alive = $master_pid && @\swoole_process::kill($master_pid, 0);
		// 判断是否已经在运行
		if ($master_is_alive) {
			if ($command === 'start') {
				self::log("SwooleSama[$start_file] already running");
				exit();
			}
		} elseif ($command !== 'start' && $command !== 'restart') {
			self::log("SwooleSama[$start_file] not run");
			exit();
		}
		self::$_statisticsFile = sys_get_temp_dir() . '/swooleSama' . $master_pid . '.status';
		// execute command.
		switch ($command) {
			case 'start':
				if ($command2 === '-d') {
					self::$_config["daemonize"] = 1;
				}
				break;
			case 'status':
				$i = 0;
				while (1) {
					// 第一次
					if ($i == 0) {
						echo ("\e[0;0H\e[2J");
						self::statusUI(file_get_contents(self::$_statisticsFile));
					} else {
						print("\033[0;0H");
						$echo_data = explode("\n", self::$safeEchoData);
						self::$safeEchoData = "";
						foreach ($echo_data as $ei => $en) {
							echo str_pad('', strlen($en)) . "\n";
						}
						print("\033[0;0H");
						self::statusUI(file_get_contents(self::$_statisticsFile));
					}
					$i ++;
					sleep(1);
				}
				exit(0);
			case 'restart':
			case 'stop':
				self::log("SwooleSama[$start_file] is stoping ...");
				// 发送停止信号
				$master_pid && \swoole_process::kill($master_pid, SIGTERM);
				// Timeout.
				$timeout = 5;
				$start_time = time();
				// Check master process is still alive?
				while (1) {
					$master_is_alive = $master_pid && \swoole_process::kill($master_pid, 0);
					if ($master_is_alive) {
						// Timeout?
						if (time() - $start_time >= $timeout) {
							self::log("SwooleSama[$start_file] stop fail");
							exit();
						}
						// Waiting amoment.
						sleep(0.1);
						continue;
					}
					// Stop success.
					self::log("SwooleSama[$start_file] stop success");
					if ($command === 'stop') {
						exit(0);
					}
					if ($command2 === '-d') {
						self::$_config["daemonize"] = 1;
					}
					break;
				}
				break;
			case 'reload':
				\swoole_process::kill($master_pid, SIGUSR1);
				self::log("SwooleSama[$start_file] reload");
				exit();
			default:
				exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
		}
	}

	/**
	 * 程序启动的时候,显示欢迎ui
	 */
	protected static function displayUI() {
		self::safeEcho("\033[1A\n\033[K-----------------------\033[47;30m " . self::$_config["name"] . " \033[0m-----------------------------\n\033[0m");
		self::safeEcho('SWOOLE version:' . swoole_version() . "          PHP version:" . PHP_VERSION . "\n");
		self::safeEcho('reactor_num:' . self::$_config["reactor_num"] . '    worker_num:' . self::$_config["worker_num"] . "    cpu_num:" . swoole_cpu_num() . "\n");
		self::safeEcho("------------------------\033[47;30m LISTEN \033[0m-------------------------------\n");
		self::safeEcho("\033[47;30mlisten\033[0m" . str_pad('', self::$_maxSocketNameLength + 2 - strlen('listen')) . "\033[47;\033[47;30m" . "status\033[0m\n");
		foreach (self::$_init_workers as $worker) {
			self::safeEcho(str_pad($worker['address'], self::$_maxSocketNameLength + 2) . " \033[32;40m [OK] \033[0m\n");
		}
		self::safeEcho("----------------------------------------------------------------\n");
		if (self::$_config["daemonize"]) {
			global $argv;
			$start_file = $argv[0];
			self::safeEcho("Input \"php $start_file stop\" to quit. Start success.\n\n");
		} else {
			self::safeEcho("Press Ctrl-C to quit. Start success.\n");
		}
	}

	/**
	 * 平滑重启
	 * https://wiki.swoole.com/wiki/page/p-server/reload.html
	 */
	public static function reload($only_reload_taskworkrer = false) {
		self::$server->reload($only_reload_taskworkrer);
	}

	/**
	 * 通过协程id，获取app实体
	 */
	public static function get_co_poll($co_id) {
		if (key_exists($co_id, self::$coroutine_app_map)) {
			return self::$coroutine_app_map[$co_id];
		}
		return null;
	}

	/**
	 * 查看服务状态
	 */
	protected static function statusUI($json) {
		// echo ("\e[1;1H\e[2J");
		// print("\033[1;1H");
		$obj = json_decode($json, true);
		self::safeEcho("\033[1A\n\033[K-----------------------\033[47;30m " . $obj["name"] . " \033[0m-----------------------------\n\033[0m");
		self::safeEcho('SWOOLE version:' . swoole_version() . "          PHP version:" . PHP_VERSION . "\n");
		foreach ($obj["listen"] as $worker) {
			self::safeEcho("\033[47;30mlisten\033[0m    " . str_pad($worker['address'], $obj["maxSocketNameLength"] + 2) . "\n");
		}
		self::safeEcho('reactor_num:' . $obj["reactor_num"] . '    worker_num:' . $obj["worker_num"] . "    cpu_num:" . swoole_cpu_num() . "    all_request_count:" . $obj["request_count"] . "\n");
		self::safeEcho("------------------------\033[47;30m WORKERS \033[0m-------------------------------\n");
		self::safeEcho("\033[47;30mpid\033[0m" . str_pad('', 7 - strlen('pid')) . "\033[47;\033[47;30m" . 
		//
		"\033[47;30mtype\033[0m" . str_pad('', 10 - strlen('type')) . "\033[47;30mmemory\033[0m" . str_pad('', 8 - strlen('memory')) . 
		//
		"\033[47;30mstart_time\033[0m" . str_pad('', 21 - strlen('start_time')) . 
		//
		"\033[47;30mconn_num\033[0m" . str_pad('', 10 - strlen('conn_num')) . "\033[47;30maccept_count\033[0m" . str_pad('', 14 - strlen('accept_count')) . 
		//
		"\033[47;30mrequest_count\033[0m" . str_pad('', 15 - strlen('request_count')) . "\033[47;30mcoroutine_num\033[0m" . str_pad('', 14 - strlen('accept_count')) . 
		//
		"\033[47;\033[47;30mstatus\033[0m\n");
		
		foreach ($obj["dead_workers"] as $worker) {
			$status = $worker["status"];
			$start_date = T($status["start_time"]);
			$worker_type = "worker";
			if ($worker["is_taskworker"]) {
				$worker_type = "task";
			}
			self::safeEcho($worker["pid"] . str_pad('', 7 - strlen($worker["pid"])) . 
			//
			$worker_type . str_pad('', 10 - strlen($worker_type)) . $worker["memory"] . str_pad('', 8 - strlen($worker["memory"])) . 
			//
			$start_date . str_pad('', 21 - strlen($start_date)) . 
			//
			$status["connection_num"] . str_pad('', 10 - strlen($status["connection_num"])) . $status["accept_count"] . str_pad('', 14 - strlen($status["accept_count"])) . 
			//
			$status["worker_request_count"] . str_pad('', 15 - strlen($status["worker_request_count"])) . $status["coroutine_num"] . str_pad('', 14 - strlen($status["coroutine_num"])) . 
			//
			"\033[31;40m [dead] \033[0m\n");
		}
		foreach ($obj["workers"] as $worker) {
			$status = $worker["status"];
			$start_date = T($status["start_time"]);
			$worker_type = "worker";
			if ($worker["is_taskworker"]) {
				$worker_type = "task";
			}
			self::safeEcho($worker["pid"] . str_pad('', 7 - strlen($worker["pid"])) . 
			//
			$worker_type . str_pad('', 10 - strlen($worker_type)) . $worker["memory"] . str_pad('', 8 - strlen($worker["memory"])) . 
			//
			$start_date . str_pad('', 21 - strlen($start_date)) . 
			//
			$status["connection_num"] . str_pad('', 10 - strlen($status["connection_num"])) . $status["accept_count"] . str_pad('', 14 - strlen($status["accept_count"])) . 
			//
			$status["worker_request_count"] . str_pad('', 15 - strlen($status["worker_request_count"])) . $status["coroutine_num"] . str_pad('', 14 - strlen($status["coroutine_num"])) . 
			//
			"\033[32;40m [ok] \033[0m\n");
		}
		self::safeEcho("----------------------------------------------------------------\n");
		self::safeEcho("Press Ctrl-C to quit.\n");
	}

	/**
	 * Safe Echo.
	 *
	 * @param
	 *        	$msg
	 */
	public static function safeEcho($msg) {
		if (! function_exists('posix_isatty') || posix_isatty(STDOUT)) {
			self::$safeEchoData = self::$safeEchoData . $msg;
			echo $msg;
		}
	}

	/**
	 * Log.
	 *
	 * @param string $msg        	
	 * @return void
	 */
	public static function log($msg) {
		$msg = $msg . "\n";
		if (! self::$_config["daemonize"]) {
			self::safeEcho($msg);
		}
		file_put_contents((string) self::$_config["log_file"], date('Y-m-d H:i:s') . ' ' . $msg, FILE_APPEND | LOCK_EX);
	}

	public static function send_worker_channel($method, $data = array()) {
		$a = array();
		$a["m"] = $method;
		$a["data"] = $data;
		self::$_workers_channel->push($a);
	}

	/**
	 * 清除缓存
	 */
	private static function clear_storage() {
		View::clear();
	}

	/**
	 * 获取协程app
	 */
	public static function getApp() {
		return self::$coroutine_app_map[\Co::getuid()];
	}
}