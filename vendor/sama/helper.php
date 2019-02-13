<?php

/**
 * 格式化时间
 */
function T($time = null, $fmt = null) {
	if ($fmt == null) {
		$fmt = "Y-m-d H:i:s";
	}
	if ($time == null || $time == "" || $time == 0) {
		return "";
	}
	return date($fmt, $time);
}

/**
 * 检查特殊字符的input
 */
function input_safe($name, $default = "") {
	$val = "";
	if (array_key_exists($name, $_GET)) {
		$val = $_GET[$name];
	} elseif (array_key_exists($name, $_POST)) {
		$val = $_POST[$name];
	}
	if ($val === "") {
		return $default;
	} else {
		$val = str_replace("and", "", $val);
		$val = str_replace("update", "", $val);
		$val = str_replace("chr", "", $val);
		$val = str_replace("delete", "", $val);
		$val = str_replace("from", "", $val);
		$val = str_replace("insert", "", $val);
		$val = str_replace("mid", "", $val);
		$val = str_replace("sleep", "", $val);
		$val = str_replace("master", "", $val);
		$val = str_replace("set", "", $val);
		$val = str_replace("union", "", $val);
		$val = str_replace("or", "", $val);
		$val = addslashes($val);
		return $val;
	}
}

/**
 * session相关操作
 */
function session($key, $val = "") {
	// 开启session，在访问最后关闭
	Http::sessionStart();
	// 获取
	if ($val === "") {
		if (array_key_exists($key, $_SESSION)) {
			$v = $_SESSION[$key];
			Http::sessionWriteClose();
			return $v;
		} else {
			Http::sessionWriteClose();
			return null;
		}
		// 删除
	} elseif ($val === null) {
		unset($_SESSION[$key]);
		Http::sessionWriteClose();
		// 设置
	} else {
		$_SESSION[$key] = $val;
		Http::sessionWriteClose();
	}
}

/**
 * 调用某个方法
 */
function fun($f) {
	return call_user_func($f);
}

// 返回js的alert脚本
function alert($str) {
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<meta content='text/html; charset=utf-8' http-equiv='Content-Type'>";
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>alert('" . $str . "');</script>";
	return true;
}

/**
 * 返回上一页的js脚本
 */
function history_back() {
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>history.back();</script>";
}

// 重定向方法,url需要传递相对于views的路径
function R($url, $arr = array()) {
	$en = strpos($url, "http");
	if ($en !== 0) {
		$url = SamaWeb::$MODULE_URL . $url;
	}
	// 跳转默认是当前模块
	if (count($arr) <= 0) {
		SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>location.href='" . $url . "';</script>";
	} else {
		$str = "";
		$str = "<form method='post' action='" . $url . "' id='my_f_fomr_d'>";
		foreach ($arr as $key => $n) {
			$str = $str . "<input name='" . $key . "' value='" . $n . "' type='hidden' />";
		}
		$str = $str . "</form>";
		$str = $str . "<script>document.getElementById('my_f_fomr_d').submit();</script>";
		SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . $str;
	}
	return true;
}

/**
 * 发送api
 */
function api($msg, $code, $content) {
	Http::header("Content-type: application/json");
	$arr["msg"] = $msg;
	$arr["code"] = $code;
	$content = json_int_to_string($content);
	$arr["content"] = $content;
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . json_encode($arr);
	return true;
}

/**
 * 返回页面
 */
function view($tpl, &$arr = array()) {
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . SamaWeb::$view->view($tpl, $arr);
	return true;
}

/**
 * 随机数
 *
 * @param
 *        	$length
 * @param string $chars        	
 * @return string
 */
function random($length, $chars = '1234567890qwertyuiopasdfghjklzxcvbnm') {
	$hash = '';
	$max = strlen($chars) - 1;
	for ($i = 0; $i < $length; $i ++) {
		$hash .= $chars[mt_rand(0, $max)];
	}
	return $hash;
}

/**
 * 清理 HTML 中的 XSS 潜在威胁
 * 参考:https://github.com/xpader/Navigation
 * 千辛万苦写出来，捣鼓正则累死人
 *
 * @param string|array $string        	
 * @param bool $strict
 *        	严格模式下，iframe 等元素也会被过滤
 * @return mixed
 */
function clean_xss($string, $strict = true) {
	if (is_array($string)) {
		return array_map('cleanXss', $string);
	}
	
	// 移除不可见的字符
	$string = preg_replace('/%0[0-8bcef]/', '', $string);
	$string = preg_replace('/%1[0-9a-f]/', '', $string);
	$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $string);
	
	$string = preg_replace('/<meta.+?>/is', '', $string); // 过滤 meta 标签
	$string = preg_replace('/<link.+?>/is', '', $string); // 过滤 link 标签
	$string = preg_replace('/<script.+?<\/script>/is', '', $string); // 过滤 script 标签
	
	if ($strict) {
		$string = preg_replace('/<style.+?<\/style>/is', '', $string); // 过滤 style 标签
		$string = preg_replace('/<iframe.+?<\/iframe>/is', '', $string); // 过滤 iframe 标签 1
		$string = preg_replace('/<iframe.+?>/is', '', $string); // 过滤 iframe 标签 2
	}
	
	$string = preg_replace_callback('/(\<\w+\s)(.+?)(?=( \/)?\>)/is', function ($m) {
		// 去除标签上的 on.. 开头的 JS 事件，以下一个 xxx= 属性或者尾部为终点
		$m[2] = preg_replace('/\son[a-z]+\s*\=.+?(\s\w+\s*\=|$)/is', '\1', $m[2]);
		
		// 去除 A 标签中 href 属性为 javascript: 开头的内容
		if (strtolower($m[1]) == '<a ') {
			$m[2] = preg_replace('/href\s*=["\'\s]*javascript\s*:.+?(\s\w+\s*\=|$)/is', 'href="#"\1', $m[2]);
		}
		
		return $m[1] . $m[2];
	}, $string);
	
	$string = preg_replace('/(<\w+)\s+/is', '\1 ', $string); // 过滤标签头部多余的空格
	$string = preg_replace('/(<\w+.*?)\s*?( \/>|>)/is', '\1\2', $string); // 过滤标签尾部多余的空格
	
	return $string;
}

/**
 * 打印
 *
 * @param
 *        	$arr
 */
function dd($arr) {
	var_dump($arr);
}

function find($model, $id) {
	return call_user_func("\model\\" . $model . "::find", $id);
}

/**
 * 日志打印
 */
function dd_log($msg, $dir = "default") {
	if (SamaWeb::$udp_log_client != null) {
		$arr["dir"] = $dir;
		$arr["msg"] = $msg;
		SamaWeb::$udp_log_client->send(json_encode($arr));
	}
}

/**
 * layer插件操作
 *
 * @param string $url        	
 */
function close_layer($cla = "null", $m = "") {
	if ($cla == "null") {
		SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>var index = parent.layer.getFrameIndex(window.name);parent.layer.close(index);</script>";
		return true;
	}
	if ($cla == "reload") {
		SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>var index = parent.layer.getFrameIndex(window.name);parent.open_reload();parent.layer.close(index);</script>";
		return true;
	}
	SamaWeb::$SEND_BODY = SamaWeb::$SEND_BODY . "<script>var index = parent.layer.getFrameIndex(window.name);parent.Sama_open('" . $cla . "','" . $m . "');parent.layer.close(index);</script>";
	return true;
}

// 从0开始
function k_to_str($k) {
	$zms = array(
		"A",
		"B",
		"C",
		"D",
		"E",
		"F",
		"G",
		"H",
		"I",
		"J",
		"K",
		"L",
		"M",
		"N",
		"O",
		"P",
		"Q",
		"R",
		"S",
		"T",
		"U",
		"V",
		"W",
		"X",
		"Y",
		"Z"
	);
	$r = "";
	if ($k < 26) {
		$r = $zms[$k];
	} else {
		$qz = (int) ($k / 26);
		$k = $k + 1;
		$r = $zms[$qz] . $zms[($k % 26) - 1];
	}
	return $r;
}

/**
 * 获取汉字首字母
 *
 * @param
 *        	$str
 * @return string|NULL
 */
function getFirstCharter($str) {
	if (empty($str)) {
		return '';
	}
	
	$fchar = ord($str{0});
	
	if ($fchar >= ord('A') && $fchar <= ord('z'))
		return strtoupper($str{0});
	
	$s1 = iconv('UTF-8', 'gb2312', $str);
	
	$s2 = iconv('gb2312', 'UTF-8', $s1);
	
	$s = $s2 == $str ? $s1 : $str;
	
	$asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
	
	if ($asc >= - 20319 && $asc <= - 20284)
		return 'A';
	
	if ($asc >= - 20283 && $asc <= - 19776)
		return 'B';
	
	if ($asc >= - 19775 && $asc <= - 19219)
		return 'C';
	
	if ($asc >= - 19218 && $asc <= - 18711)
		return 'D';
	
	if ($asc >= - 18710 && $asc <= - 18527)
		return 'E';
	
	if ($asc >= - 18526 && $asc <= - 18240)
		return 'F';
	
	if ($asc >= - 18239 && $asc <= - 17923)
		return 'G';
	
	if ($asc >= - 17922 && $asc <= - 17418)
		return 'H';
	
	if ($asc >= - 17417 && $asc <= - 16475)
		return 'J';
	
	if ($asc >= - 16474 && $asc <= - 16213)
		return 'K';
	
	if ($asc >= - 16212 && $asc <= - 15641)
		return 'L';
	
	if ($asc >= - 15640 && $asc <= - 15166)
		return 'M';
	
	if ($asc >= - 15165 && $asc <= - 14923)
		return 'N';
	
	if ($asc >= - 14922 && $asc <= - 14915)
		return 'O';
	
	if ($asc >= - 14914 && $asc <= - 14631)
		return 'P';
	
	if ($asc >= - 14630 && $asc <= - 14150)
		return 'Q';
	
	if ($asc >= - 14149 && $asc <= - 14091)
		return 'R';
	
	if ($asc >= - 14090 && $asc <= - 13319)
		return 'S';
	
	if ($asc >= - 13318 && $asc <= - 12839)
		return 'T';
	
	if ($asc >= - 12838 && $asc <= - 12557)
		return 'W';
	
	if ($asc >= - 12556 && $asc <= - 11848)
		return 'X';
	
	if ($asc >= - 11847 && $asc <= - 11056)
		return 'Y';
	
	if ($asc >= - 11055 && $asc <= - 10247)
		return 'Z';
	
	return null;
}

/**
 * 获取两个字符串之间内容
 */
function get_between($input, $start, $end) {
	$start_i = strpos($input, $start) + strlen($start);
	$input = substr($input, $start_i);
	$end_i = strpos($input, $end);
	$input = substr($input, 0, $end_i);
	return $input;
}
