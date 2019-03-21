<?php
namespace test\model;

use sama\db\Db;

class User extends Db {
	public static $table = "t_user";
	public static $softDelete = true;
	
}
