<?php
namespace sama\process;

use sama\App;
use sama\Sama;

class Task {
	
	public static function onTask($server, $task_id, $reactor_id, $data) {
		echo "New AsyncTask[id=$task_id]\n";
		$server->finish("$data -> OK");
	}
	
	/**
	 * tcp消息
	 */
	public static function onFinish($server, $task_id, $data){
		echo "AsyncTask[$task_id] finished: {$data}\n";
	}
	
}