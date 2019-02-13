<?php
namespace sama\view;

use sama\Sama;

/**
 * @bean(Sama.sama.view.view)
 */
class View {

	/*
	 * The name of the directory where templates are located.
	 * @var string
	 */
	public $templatedir = "";

	/*
	 * The directory where compiled templates are located.
	 * @var string
	 */
	public $compiledir = "";

	/*
	 * where assigned template vars are kept
	 * @var array
	 */
	public $vars = array();

	/**
	 * 存放视图，视图缓存对应表
	 *
	 * @var array
	 */
	public $tpl_storage = array();

	/*
	 * compile a resource
	 * sets PHP tag to the compiled source
	 * @param string $tpl (template file)
	 */
	public function parse($tpl_dir, $tpl) {
		// load template file //
		$fp = @fopen($tpl_dir . $tpl, 'r');
		$text = fread($fp, filesize($tpl_dir . $tpl));
		fclose($fp);
		// repalce template tag to PHP tag //
		$text = str_replace('{/if}', '<?php } ?>', $text);
		$text = str_replace('{/loop}', '<?php } ?>', $text);
		$text = str_replace('{foreachelse}', '<?php } else {?>', $text);
		$text = str_replace('{/foreach}', '<?php } ?>', $text);
		$text = str_replace('{else}', '<?php } else {?>', $text);
		$text = str_replace('{loopelse}', '<?php } else {?>', $text);
		// template pattern tags //
		$pattern = array(
			'/\$(\w*[a-zA-Z0-9_])/',
			'/\$this\-\>vars\[\'(\w*[a-zA-Z0-9_])\'\]+\.(\w*[a-zA-Z0-9])/',
			'/\{include file=(\"|\'|)(\w*[a-zA-Z0-9_\.][a-zA-Z]\w*)(\"|\'|)\}/',
			'/\{\$this\-\>vars(\[\'(\w*[a-zA-Z0-9_])\'\])(\[\'(\w*[a-zA-Z0-9_])\'\])?\}/',
			'/\{if (.*?)\}/',
			'/\{elseif (.*?)\}/',
			'/\{loop \$(.*) as (\w*[a-zA-Z0-9_])\}/',
			'/\{foreach \$(.*) (\w*[a-zA-Z0-9_])\=\>(\w*[a-zA-Z0-9_])\}/'
		);
		// replacement PHP tags //
		$replacement = array(
			'$this->vars[\'\1\']',
			'$this->vars[\'\1\'][\'\2\']',
			'<?php echo $this->display(\'\2\')?>',
			'<?php echo \$this->vars\1\3?>',
			'<?php if(\1) {?>',
			'<?php } elseif(\1) {?>',
			'<?php if (count((array)\$\1)) foreach((array)\$\1 as \$this->vars[\'\2\']) {?>',
			'<?php if (count((array)\$\1)) foreach((array)\$\1 as \$this->vars[\'\2\']=>$this->vars[\'\3\']) {?>'
		);
		// repalce template tags to PHP tags //
		$text = preg_replace($pattern, $replacement, $text);
		
		// create compile file //
		$compliefile = time() . random(10) . "_" . Sama::$server->worker_pid . ".tmp";
		if ($fp = @fopen($this->compiledir . $compliefile, 'w')) {
			fputs($fp, $text);
			fclose($fp);
		}
		// 删除旧的模板
		@unlink($this->compiledir . $this->tpl_storage[$tpl]);
		$this->tpl_storage[$tpl] = $compliefile;
	}

	/*
	 * assigns values to template variables
	 * @param array|string $k the template variable name(s)
	 * @param mixed $v the value to assign
	 */
	public function assign($k, $v = null) {
		$this->vars[$k] = $v;
	}

	/*
	 * ste directory where templates are located
	 * @param string $str (path)
	 */
	public function templateDir($path) {
		$this->templatedir = self::pathCheck($path);
	}

	/*
	 * set where compiled templates are located
	 * @param string $str (path)
	 */
	public function compileDir($path) {
		$this->compiledir = self::pathCheck($path);
	}

	/*
	 * check the path last character
	 * @param string $str (path)
	 * @return string
	 */
	public static function pathCheck($str) {
		return (preg_match('/\/$/', $str)) ? $str : $str . '/';
	}

	/*
	 * executes & displays the template results
	 * @param string $tpl (template file)
	 */
	public function display($tpl_dir, $tpl) {
		// 将.替换成/
		$tpl = str_replace('.', '/', $tpl);
		$tpl = $tpl . ".html";
		
		if (! file_exists($tpl_dir . $tpl)) {
			return ('can not load template file : ' . $tpl_dir . $tpl);
		}
		// 判断是否存在这个模板缓存
		if (array_key_exists($tpl, $this->tpl_storage)) {
			// 获取模板模板缓存位置
			$compliefile = $this->compiledir . $this->tpl_storage[$tpl];
			if (! file_exists($compliefile) || filemtime($tpl_dir . $tpl) > filemtime($compliefile)) {
				$this->parse($tpl_dir, $tpl);
			}
		} else {
			// 如果不存在这个模板缓存
			$this->parse($tpl_dir, $tpl);
		}
		$compliefile = $this->compiledir . $this->tpl_storage[$tpl];
		foreach ($this->vars as $k => $n) {
			$$k = $n;
		}
		// 打开缓存区
		ob_start();
		include $compliefile;
		$contents = ob_get_contents();
		ob_end_clean();
		
		return $contents;
	}

	public function __construct() {
		$this->compileDir(RUN_DIR . Sama::$_config['view_storage_dir']);
		$this->templateDir(RUN_DIR . Sama::$_config['view_template_dir']);
	}

	/**
	 * 渲染
	 */
	public function view($app, $tpl_dir, $tpl, &$vars = array()) {
		if ($tpl_dir == "") {
			$tpl_dir = $this->templatedir;
		}
		$this->vars = $vars;
		$this->vars["HOME"] = $app->request->server["home"] . "/";
		$ret = $this->display($tpl_dir, $tpl);
		$this->vars = null;
		return $ret;
	}

	/**
	 * 清空模板缓存
	 */
	public static function clear() {
		foreach (glob(self::pathCheck(Sama::$_config['view_storage_dir']) . '*.tmp') as $start_file) {
			unlink($start_file);
		}
	}
}
