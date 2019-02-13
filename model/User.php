<?php
namespace model;

use tofu\db\MysqlPool;

class User extends MysqlPool {
	public static $table = "t_user";
	public static $softDelete = true;
}
