<?php

class IndexController extends Controller {
	public function index() {
		
		$simple = from('source')->select('a','b','c')->where->eq('a', _);
	}
}
