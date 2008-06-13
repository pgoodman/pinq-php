<?php

class IndexController extends Controller {
	public function index() {
		
		// single-source
		$simple = from('source')->select('a','b','c')->where->eq('a', _);
		
		// multi-source
		$complex = from('sourcea', 'a')->select('a','b')->
		           from('soueceb', 'b')->select('c')->where->
		           a('a')->eq(_)->and->b('c')->eq->a('b');
		
		print_r($simple);
		print_r($complex);
	}
}
