<?php
namespace model;

use sama\db\MysqlPool;

class User extends MysqlPool {
	public static $table = "t_user";
	public static $softDelete = true;
}
