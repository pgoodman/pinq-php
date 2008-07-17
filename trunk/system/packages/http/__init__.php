<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqHttp implements ConfigurablePackage {
	
	protected $_models,
	          $_ds,
	          $_gateway_name;
	
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {

		extract($args);

		if(0 === $argc) {
			throw new InvalidPackageException(
				""
			);
		}
		
		$dir = DIR_APPLICATION ."/models/http/{$argv[0]}";
		
		if(!is_dir($dir)) {
			
		}
		
		return new $class($dir);
	}
	
	public function __construct($dir) {
		$this->_gateway_dir = $dir;
	}
	
	public function __get($model_name) {
		return $this->__call($model_name);
	}
	
	public function __call($model_name, array $route_parts = array()) {
		
		$gateway_id = $model_name . implode('/', $route_parts);
		
		if(isset($this->_models[$gateway_id]))
			return $this->_models[$gateway_id];
		
		$file = DIR_APPLICATION ."/models/http//{$model_name}";
	}
}
