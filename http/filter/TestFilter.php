<?php
namespace http\filter;

/**
 * @bean(testFilter)
 */
class TestFilter {

	public function _before($app) {
		//dd("filterBefore");
		return true;
	}

	public function _after($app) {
		return true;
	}
}