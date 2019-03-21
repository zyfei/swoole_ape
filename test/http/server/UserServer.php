<?php
namespace test\http\server;

/**
 * @bean(userServer)
 */
class UserServer {
	
	/** @Resource(userServer) */
	public $userServer;
	
	public function login(){
		$this->userServer->login2();
	}
	
	public function login2(){
		dd("UserServer::login2~~~~~~~~~~~~~~~~~~~~~~~~~");
	}
}