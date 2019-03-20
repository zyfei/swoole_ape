<?php
/**
 * 标签列表
 * 标签分为三个级别
 * level 1 2 3三个级别
 */
return array(
	// 类上的标签
	"class" => array(
		"bean" => array(
			"value" => "\\sama\\tag\\ClassTag::bean",
			"level" => 1
		),
		"controller" => array(
			"value" => "\\sama\\tag\\ClassTag::controller",
			"level" => 1
		),
		"view" => array(
			"value" => "\\sama\\tag\\ClassTag::view",
			"level" => 1
		),
		"aop" => array(
			"value" => "\\sama\\tag\\AopTag::aop",
			"level" => 2
		)
	),
	// 方法上的s标签
	"method" => array(
		"mapping" => array(
			"value" => "\\sama\\tag\\MethodTag::mapping",
			"level" => 3
		)
	)
);