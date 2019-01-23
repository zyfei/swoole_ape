<?php
use model\User;
use ape\App;
use ape\view\View;

/**
 * 高性能框架
 */
class Ape {

	/**
	 * 全局配置
	 */
	public static $_config = null;

	/**
	 * 主server实例
	 */
	public static $server = null;

	/**
	 * 初始化时候的worker
	 */
	private static $_init_workers = array();

	/**
	 * 进程通道,manager进程使用
	 */
	public static $_workers_channel = null;

	/**
	 * 使用status命令时候数据
	 */
	public static $_globalStatistics = array();

	public static $_statisticsFile = null;

	/**
	 * 记录所有进程状态,manager进程使用
	 */
	public static $workers = array();

	public static $dead_workers = array();

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
	private static $_middleware_maps = array();

	/**
	 * 定义了标签的类
	 */
	private static $_need_load_class_arr = array();

	/**
	 * 协程信息数组，保存协程id=>app格式的关系
	 */
	private static $co_pool = array();

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
	 * 初始化框架
	 */
	public static function config($config = array()) {
		self::$_config = require_once '_lib/config.php';
		foreach ($config as $k2 => $n2) {
			self::$_config[$k2] = $n2;
		}
	}

	private static function create_beans() {
		// 创建bean
		foreach (self::$_need_load_class_arr as $obj) {
			$ref = new ReflectionClass($obj);
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
		$tags = require_once RUN_DIR . '/_lib/ape/tag/tags.php';
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
	 * 读取所有的php文件
	 */
	private static function loadPhpFiles() {
		$dir_iterator = new RecursiveDirectoryIterator(RUN_DIR);
		$iterator = new RecursiveIteratorIterator($dir_iterator);
		foreach ($iterator as $file) {
			// only check php files
			if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
				continue;
			}
			$namespace = str_replace(RUN_DIR, "", $file->getPath());
			$namespace = str_replace(DIRECTORY_SEPARATOR . Autoloader::$_libPath, "", $namespace);
			
			$file_name = (string) $file;
			$contents = file_get_contents($file_name);
			$preg = '/class [\s\S]*? /';
			preg_match_all($preg, $contents, $res);
			foreach ($res[0] as $k => $n) {
				$className = trim(get_between($n, "class ", ' '));
				$className = $namespace . DIRECTORY_SEPARATOR . $className;
				$className = substr($className, 1);
				// 判断是否是类并加载
				if ($k == 0) {
					if (! Autoloader::loadByNamespace($className)) {
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

	/**
	 * 读取并解析类的注释。不读取静态方法
	 */
	private static function loadClassTag($obj) {
		$ref = new ReflectionClass($obj);
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
	public static function loadMethodTag($_need_load_class_arr_tag, $class_tag) {
		$ref = new ReflectionClass(self::getBean($class_tag));
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
	 * 添加监听
	 */
	public static function listen($address = null, $config = array()) {
		if (self::$_config == null) {
			self::$_config = require_once '_lib/config.php';
		}
		if ($address === null) {
			throw new \Exception('address is null');
		}
		$address_info = parse_url($address);
		if (! isset($address_info['scheme'])) {
			throw new \Exception('scheme is null');
		}
		if (! isset($address_info['port'])) {
			$address_info['port'] = 80;
		}
		if (! key_exists($address_info['scheme'], self::$_builtinTransports)) {
			throw new \Exception('not support ' . $address_info['scheme']);
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
	 * 开始web服务
	 */
	public static function run() {
		if (count(self::$_init_workers) <= 0) {
			throw new \Exception('address is null');
		}
		foreach (self::$_init_workers as $k => $n) {
			if ($k == 0) {
				self::$server = new swoole_server($n["host"], $n["port"], self::$_config["server_mode"], self::$_builtinTransports[$n["scheme"]]);
				$config = self::$_config;
				foreach (self::$_builtinConfigs[$n["scheme"]] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				foreach ($n["config"] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				self::$server->set($config);
			} else {
				$config = self::$_builtinConfigs[$n["scheme"]];
				foreach ($n["config"] as $k2 => $n2) {
					$config[$k2] = $n2;
				}
				$s2 = self::$server->addListener($n["host"], $n["port"], self::$_builtinTransports[$n["scheme"]])->set($config);
			}
		}
		
		/**
		 * manager进程启动
		 */
		self::$server->on('managerStart', function ($server) {
			self::$_statisticsFile = sys_get_temp_dir() . '/swooleape' . $server->master_pid . '.status';
			swoole_set_process_name('swoole_ape: manager process');
			sleep(1);

			// 每10秒处理一次数据
			function exec_statisticsFile() {
				while (\Ape::$_workers_channel->stats()["queue_num"] > 0) {
					$a = \Ape::$_workers_channel->pop();
					if ($a["m"] == "worker") {
						$worker = $a["data"];
						\Ape::$workers[$worker['pid']] = $worker;
					}
				}
				foreach (\Ape::$workers as $k => $n) {
					// 如果不存活了
					if (! swoole_process::kill($k, 0)) {
						\Ape::$dead_workers[$k] = $n;
						unset(\Ape::$workers[$k]);
					}
				}
				\Ape::$_globalStatistics["workers"] = \Ape::$workers;
				\Ape::$_globalStatistics["dead_workers"] = \Ape::$dead_workers;
				\Ape::$_globalStatistics["request_count"] = \Ape::$server->stats()["request_count"];
				file_put_contents(\Ape::$_statisticsFile, json_encode(\Ape::$_globalStatistics));
			}
			exec_statisticsFile();
			swoole_timer_tick(3000, function () {
				exec_statisticsFile();
			});
		});
		self::$server->on('workerStart', function ($server, $worker_id) {
			global $argv;
			if ($server->taskworker) {
				swoole_set_process_name('swoole_ape: task_worker process  ' . self::$_config["name"]);
			} else {
				swoole_set_process_name('swoole_ape: worker process  ' . self::$_config["name"]);
			}
			// 清空缓存
			if ($worker_id == 0) {
				View::clear();
			}
			go(function () use ($server) {

				function worder_channel($server) {
					$data = array();
					$data["is_taskworker"] = $server->taskworker;
					$data["worker_id"] = $server->worker_id;
					$data["pid"] = $server->worker_pid;
					$data["memory"] = round(memory_get_usage(true) / (1024 * 1024), 2) . "M";
					$data["status"] = $server->stats();
					// 通知manager进程(注意不是master进程)
					\Ape::send_worker_channel("worker", $data);
				}
				worder_channel($server);
				// 3秒刷新一次
				swoole_timer_tick(3000, function () use ($server) {
					worder_channel($server);
				});
			});
		});
		
		/**
		 * tcp请求触发
		 */
		self::$server->on('receive', function ($server, $fd, $reactor_id, $data) {
			echo "receive \n";
		});
		
		/**
		 * http请求触发
		 */
		self::$server->on('request', function ($request, $response) {
			$app = new App();
			$app->setHttp($request, $response);
			self::add_co_poll(\Co::getuid(), $app);
			// 升级到最新版终于致辞defer了
			defer(function () use ($app) {
				// 在这里回收mysql资源以及一些其他操作
				$app->free_mysql();
				self::clear_co_poll(\Co::getuid());
				unset($app);
			});
			
			$return_str = "";
			if (key_exists($request->server['path_info'], self::$http_route_maps)) {
				$http_arr = self::$http_route_maps[$request->server['path_info']];
				$app->route_map = $http_arr;
				$obj = self::getBean($http_arr['cla']);
				$method = $http_arr['method'];
				if (key_exists($http_arr['cla'], self::$_middleware_maps)) {
					foreach (self::$_middleware_maps[$http_arr['cla']] as $m) {
						$return_t = self::getBean($m)->_before($app);
						if ($return_t === false) {
							$app->end();
							return;
						}
					}
				}
				$return_str = $obj->$method($app);
				if (is_array($return_str) || is_object($return_str)) {
					$return_str = json_encode($return_str);
				}
				if (key_exists($http_arr['cla'], self::$_middleware_maps)) {
					foreach (self::$_middleware_maps[$http_arr['cla']] as $m) {
						$return_t = self::getBean($m)->_after($app);
						if ($return_t === false) {
							$app->end();
							return;
						}
					}
				}
			}
			$app->end($return_str);
		});
		
		/**
		 * WebSocket请求触发
		 */
		self::$server->on('message', function ($server, $frame) {
			echo "message \n";
		});
		
		/**
		 * 接收到UDP数据包时回调此函数
		 */
		self::$server->on('packet', function ($server, $data, $addr) {
			echo "packet \n";
		});
		
		self::$server->on('task', function ($server, $task_id, $reactor_id, $data) {
			echo "New AsyncTask[id=$task_id]\n";
			$server->finish("$data -> OK");
		});
		self::$server->on('finish', function ($server, $task_id, $data) {
			echo "AsyncTask[$task_id] finished: {$data}\n";
		});
		self::$server->start();
	}

	public static function runAll() {
		swoole_set_process_name('swoole_ape: master process');
		// 创建队列,最大1024个容量
		self::$_workers_channel = new Swoole\Channel(1024);
		self::$workers = array();
		self::$_globalStatistics['name'] = self::$_config["name"];
		self::$_globalStatistics['start_time'] = time();
		self::$_globalStatistics['reactor_num'] = self::$_config["reactor_num"];
		self::$_globalStatistics['worker_num'] = self::$_config["worker_num"];
		self::$_globalStatistics['listen'] = self::$_init_workers;
		self::$_globalStatistics['maxSocketNameLength'] = self::$_maxSocketNameLength;
		self::parseCommand();
		self::loadPhpFiles();
		self::create_beans();
		self::create_tags();
		self::displayUI();
		self::run();
	}

	/**
	 * 平滑重启
	 * https://wiki.swoole.com/wiki/page/p-server/reload.html
	 */
	public static function reload($only_reload_taskworkrer = false) {
		self::$server->reload($only_reload_taskworkrer);
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
		
		$c = (new ReflectionClass($cla))->newInstanceWithoutConstructor();
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

	/**
	 * 添加协程关系
	 */
	public static function add_co_poll($co_id, $app) {
		self::$co_pool[$co_id] = $app;
	}

	public static function get_co_poll($co_id) {
		if (key_exists($co_id, self::$co_pool)) {
			return self::$co_pool[$co_id];
		}
		return null;
	}

	public static function clear_co_poll($co_id) {
		unset(self::$co_pool[$co_id]);
	}

	/**
	 * 程序启动的时候,显示欢迎ui
	 */
	/**
	 * Display staring UI.
	 *
	 * @return void
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
	 * 查看服务状态
	 */
	protected static function statusUI($json) {
		//echo ("\e[1;1H\e[2J");
		//print("\033[1;1H");
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
		self::log("SwooleApe[$start_file] $command $mode");
		
		$master_pid = @file_get_contents(self::$_config["pid_file"]);
		$master_is_alive = $master_pid && @swoole_process::kill($master_pid, 0);
		// 判断是否已经在运行
		if ($master_is_alive) {
			if ($command === 'start') {
				self::log("SwooleApe[$start_file] already running");
				exit();
			}
		} elseif ($command !== 'start' && $command !== 'restart') {
			self::log("SwooleApe[$start_file] not run");
			exit();
		}
		self::$_statisticsFile = sys_get_temp_dir() . '/swooleape' . $master_pid . '.status';
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
						//sleep(1);
						print("\033[0;0H");
						self::statusUI(file_get_contents(self::$_statisticsFile));
					}
					$i ++;
					sleep(1);
					// var_dump($i.'\n');
				}
				exit(0);
			case 'restart':
			case 'stop':
				self::log("SwooleApe[$start_file] is stoping ...");
				// 发送停止信号
				$master_pid && swoole_process::kill($master_pid, SIGTERM);
				// Timeout.
				$timeout = 5;
				$start_time = time();
				// Check master process is still alive?
				while (1) {
					$master_is_alive = $master_pid && swoole_process::kill($master_pid, 0);
					if ($master_is_alive) {
						// Timeout?
						if (time() - $start_time >= $timeout) {
							self::log("SwooleApe[$start_file] stop fail");
							exit();
						}
						// Waiting amoment.
						sleep(0.1);
						continue;
					}
					// Stop success.
					self::log("SwooleApe[$start_file] stop success");
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
				swoole_process::kill($master_pid, SIGUSR1);
				self::log("SwooleApe[$start_file] reload");
				exit();
			default:
				exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
		}
	}

	public static function send_worker_channel($method, $data = array()) {
		$a = array();
		$a["m"] = $method;
		$a["data"] = $data;
		self::$_workers_channel->push($a);
	}
}