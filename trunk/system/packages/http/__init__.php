<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqHttp implements ConfigurablePackage {
	
	protected $_models,
	          $_data_source,
	          $_gateway_name;
	
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {

		extract($args);
		
		$argv[] = ''; // centinel
		$dir = DIR_APPLICATION ."/models/http/{$argv[0]}";
		
		if(0 === $argc || !is_dir($dir)) {
			throw new InvalidArgumentException(
				"No models exist for the HTTP gateway [{$argv[0]}] or no ".
				"gateway was provided."
			);
		}
		
		return new $class($dir);
	}
	
	protected function __construct($dir, DataSource $ds) {
		$this->_gateway_dir = $dir;
		$this->_data_source = $ds;
	}
	
	public function __get($model_name) {
		return $this->__call($model_name);
	}
	
	public function __call($model_name, array $route_parts = array()) {
		
		$gateway_id = $model_name . implode('/', $route_parts);
		
		if(isset($this->_models[$gateway_id]))
			return $this->_models[$gateway_id];
		
	}
}
