<?php

class IndexController extends Controller {
	public function index() {
		
		print_r($_SERVER);
		
		var_dump(get_url());
				
		//var_dump(get_http_host());
		//var_dump(gethostbyname('pgoodman.dev.www.ere.net'));
	}
}
