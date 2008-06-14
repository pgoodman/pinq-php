<?php

class IndexController extends Controller {
	public function index() {
		
		$db = $this->import('db.blog');
		
		// single-source, if jump straight to where, then there is an implicit
		// ->select(ALL)
		$simple = from('source')->where->eq('a', _);
		
		// multi-source
		$complex = from('sourcea', 'a')->select(ALL)->
		           from('soueceb', 'b')->select(ALL)->where->
		           a('a')->eq(_)->and->b('c')->eq->a('b');
		
		print_r($simple);
		print_r($complex);
	}
}
