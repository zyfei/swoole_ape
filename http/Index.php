<?php
namespace http;

use model\User;
use sama\App;

/**
 * @httpController(/a)
 * @middleware(testFilter)
 * @view(/view2)
 */
class Index {

	/**
	 * @mapping(/a)
	 *
	 * @method (post|get)
	 */
	public function aaa(\sama\App $app) {
		$app->send("hello world");
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
		
		$users = User::get(25);
		// $users = User::count("id=?",array(24));
		$users = User::page(0, 100, "id!=?", 25, "id desc");
		
		return $app->view("login", $users);
	}

	public function bbb($app) {
		dd("bbb");
		return "bbbb";
	}
}