<?php
namespace test\http;

use sama\App;
use sama\exception\Exception;

/**
 * @controller(/a)
 * @view(/view2)
 */
class Index {

	/** @Resource(userServer) */
	public $userServer;

	/** @mapping (/a) */
	public function aaa(\sama\App $app) {
		$a = md5("aaaa");
		$app->send("hello world");
		$this->userServer->login();
		// $user= array();
		// $user['id'] = 23;
		// $user["phone"] = 2;
		// User::update($user);
		// $users = User::all();
		// $users = json_encode($users);
		// $response->write("1234");
		// dd("aaaa");
		
		// User::delete(23);
		
		// User::delete_all("id=?",array(24));
		// $users = User::all("id!=1");
		// dd($users);
		// $users = Db::query('select * from t_user');
		// $users = User::get(25);
		// $users = User::count("id=?",array(24));
		// $users = User::page(0, 100, "id!=?", 25, "id desc");
		// $arr["aa"] = '{}';
		// return "abcd";
		// return $app->view("login", $arr);
	}

	public function bbb($app) {
		throw new Exception("测试错误", 301);
		return "bbbb";
	}
}
