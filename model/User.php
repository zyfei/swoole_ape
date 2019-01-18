<?php
namespace model;

use ape\db\MysqlPool;

class User extends MysqlPool {
	public static $table = "t_user";
	public static $softDelete = true;
}
