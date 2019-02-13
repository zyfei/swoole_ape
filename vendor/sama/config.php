<?php
/**
 * 公共配置
 * https://wiki.swoole.com/wiki/page/274.html
 */
return array(
	"name" => "SWOOLE_Sama",
	
	// 是否开启守护进程,默认不开启
	"daemonize" => 0,
	
	// 运行的模式 SWOOLE_PROCESS多进程模式（默认） SWOOLE_BASE基本模式
	"server_mode" => SWOOLE_PROCESS,
	
	// 通过此参数来调节Reactor线程的数量，以充分利用多核。考虑到操作系统调度存在一定程度的性能损失，可以设置为CPU核数*2
	"reactor_num" => swoole_cpu_num() * 2,
	
	// 业务代码是全异步非阻塞的，这里设置为CPU的1-4倍最合理
	"worker_num" => swoole_cpu_num() * 4,
	
	// 配置此参数后将会启用task功能,Task进程是同步阻塞的，配置方式与Worker同步模式一致,最大值不得超过SWOOLE_CPU_NUM * 1000
	// Task进程内不能使用swoole_server->task方法
	// Task进程内不能使用swoole_mysql、swoole_redis、swoole_event等异步IO函数
	"task_worker_num" => 0,
	
	// 设置task进程与worker进程之间通信的方式。
	// 1, 使用unix socket通信，默认模式 2, 使用消息队列通信 3, 使用消息队列通信，并设置为争抢模式
	// https://wiki.swoole.com/wiki/page/296.html
	"task_ipc_mode" => 1,
	
	// 设置task进程的最大任务数。一个task进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出。如果不希望进程自动退出可以设置为0。
	"task_max_request" => 0,
	
	// 设置task的数据临时目录，在swoole_server中，如果投递的数据超过8192字节，将启用临时文件来保存数据。这里的task_tmpdir就是用来设置临时文件保存的位置。
	"task_tmpdir" => "/tmp",
	
	// 数据包分发策略。可以选择3种类型，默认为2。 这里设置为1 -> 轮循模式，收到会轮循分配给每一个worker进程
	// https://wiki.swoole.com/wiki/page/277.html
	"dispatch_mode" => 1,
	
	// 错误日志文件
	// 开启守护进程模式后(daemonize => true)，标准输出将会被重定向到log_file。在PHP代码中echo/var_dump/print等打印到屏幕的内容会写入到log_file文件
	"log_file" => RUN_DIR . DIRECTORY_SEPARATOR . "echo.log",
	
	// 设置Server错误日志打印的等级，范围是0-5。低于log_level设置的日志信息不会抛出。
	// 0 => SWOOLE_LOG_DEBUG 1 => SWOOLE_LOG_TRACE 2 => SWOOLE_LOG_INFO 3 => SWOOLE_LOG_NOTICE 4 => SWOOLE_LOG_WARNING 5 => SWOOLE_LOG_ERROR
	"log_level" => 0,
	
	// 启用心跳检测，此选项表示每隔多久轮循一次，单位为秒。如 heartbeat_check_interval => 60，表示每60秒，遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。
	"heartbeat_check_interval" => 60,
	// 与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
	"heartbeat_idle_time" => 300,
	
	// 启用CPU亲和性设置。在多核的硬件平台中，启用此特性会将swoole的reactor线程/worker进程绑定到固定的一个核上。可以避免进程/线程的运行时在多个核之间互相切换，提高CPU Cache的命中率。
	"open_cpu_affinity" => 1,
	
	// 默认情况下，发送数据采用Nagle 算法。这样虽然提高了网络吞吐量，但是实时性却降低了，在一些交互性很强的应用程序来说是不允许的，使用TCP_NODELAY选项可以禁止Nagle 算法。
	"open_tcp_nodelay" => 1,
	
	// 在Server启动时自动将master进程的PID写入到文件，在Server关闭时自动删除PID文件。
	"pid_file" => RUN_DIR . DIRECTORY_SEPARATOR . "server.pid",
	
	// 配置发送输出缓存区内存尺寸,每个进程现在32M
	// 单位为字节，默认为2M，如设置32 * 1024 *1024表示，单次Server->send最大允许发送32M字节的数据
	// 调用swoole_server->send， swoole_http_server->end/write，swoole_websocket_server->push 等发送数据指令时，单次最大发送的数据不得超过buffer_output_size配置。
	// 注意此函数不应当调整过大，避免拥塞的数据过多，导致吃光机器内存
	// 开启大量worker进程时，将会占用worker_num * buffer_output_size字节的内存
	"buffer_output_size" => 32 * 1024 * 1024,
	
	// 配置客户端连接的缓存区长度
	// https://wiki.swoole.com/wiki/page/612.html
	"socket_buffer_size" => 128 * 1024 * 1024,
	
	// 端口复用
	"enable_reuse_port" => false,
	
	// 启用Http协议处理，Swoole\Http\Server会自动启用此选项。设置为false表示关闭Http协议处理。
	"open_http_protocol" => true,
	
	// 启用HTTP2协议解析，需要依赖--enable-http2编译选项。默认为false
	"open_http2_protocol" => false,
	
	// 启用websocket协议处理，Swoole\WebSocket\Server会自动启用此选项。设置为false表示关闭websocket协议处理。
	// 设置open_websocket_protocol选项为true后，会自动设置open_http_protocol协议也为true。
	"open_websocket_protocol" => false,
	
	// 启用websocket协议中关闭帧（opcode为0x08的帧）在onMessage回调中接收，默认为false。
	// 开启后，可在WebSocketServer中的onMessage回调中接收到客户端或服务端发送的关闭帧，开发者可自行对其进行处理。
	// 实例 https://wiki.swoole.com/wiki/page/974.html
	"open_websocket_close_frame" => false,
	
	// 启用mqtt协议处理，启用后会解析mqtt包头，worker进程onReceive每次会返回一个完整的mqtt数据包。
	"open_mqtt_protocol" => false,
	
	// 设置异步重启开关。设置为true时，将启用异步安全重启特性，Worker进程会等待异步事件完成后再退出。
	// 在4.x版本中开启enable_coroutine时，底层会额外增加一个协程数量的检测。当前无任何协程时进程才会退出。
	"reload_async" => true,
	
	// 开启TCP快速握手特性。此项特性，可以提升TCP短连接的响应速度，在客户端完成握手的第三步，发送SYN包时携带数据。
	"tcp_fastopen" => true,
	
	// 开启请求慢日志。启用后Manager进程会设置一个时钟信号，定时侦测所有Task和Worker进程，一旦进程阻塞导致请求超过规定的时间，将自动打印进程的PHP函数调用栈。
	// 底层基于ptrace系统调用实现，某些系统可能关闭了ptrace，无法跟踪慢请求。请确认kernel.yama.ptrace_scope内核参数是否0。
	"request_slowlog_file" => RUN_DIR . DIRECTORY_SEPARATOR . "request_slow.log",
	"request_slowlog_timeout" => 1, // 1秒
	"trace_event_worker" => true,
	
	// 设置当前工作进程最大协程数量。超过max_coroutine底层将无法创建新的协程，底层会抛出错误，并直接关闭连接。
	// 在Server程序中实际最大可创建协程数量等于 worker_num * max_coroutine
	// "max_coroutine" => 200,
	
	// 配置静态文件根目录，与enable_static_handler配合使用。
	// 设置document_root并设置enable_static_handler为true后，底层收到Http请求会先判断document_root路径下是否存在此文件，如果存在会直接发送文件内容给客户端，不再触发onRequest回调。
	'document_root' => RUN_DIR . DIRECTORY_SEPARATOR . 'static',
	'enable_static_handler' => true,
	
	// 启用压缩。默认为开启。http-chunk不支持分段单独压缩, 已强制关闭压缩.
	// 目前支持gzip、br、deflate 三种压缩格式，底层会根据浏览器客户端传入的Accept-Encoding头自动选择压缩方式。
	'http_compression' => true,
	
	// 视图模板地址
	'view_template_dir' => DIRECTORY_SEPARATOR . 'view',
	// 视图缓存地址
	'view_storage_dir' => DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . "view"
);