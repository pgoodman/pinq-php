<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Decorator for the dictionary that adds semantic meaning to adding models
 * to the dictionary.
 * @author Peter Goodman
 */
class PinqModelLoader extends Loader implements ConfigurablePackage {
	
	/**
	 * Configure the model loader by having it store an instance of itself in
	 * the package loader.
	 */
	static public function configure(Loader $loader, Loader $config, array $args) {
		DEBUG_MODE && expect_array_keys($args, array('class'));
		$class = $args['class'];
		return new $class;
	}
	
	/**
	 * Load a model file and store its models.
	 */
	public function &load($key, array $context = array()) {
		$ret = NULL;
		$file = str_replace('.', '/', $key);
		
		// the possible file names for this configuration file
		$files = array(
			DIR_APPLICATION ."/models/{$file}". EXT,
			DIR_SYSTEM ."/models/{$file}.php",
		);
				
		// figure out which file it is
		do {
			if(file_exists($file_name = array_shift($files)))
				break;
			
			$file_name = NULL;
		} while(!empty($files));
		
		// no models defined for this key, ignore it (models aren't required)
		if(NULL === $file_name)
			return $ret;
		
		// bring in the file and have it store the models
		$model = $this;
		include_once $file_name;
		
		
		$ret = $this->offsetGet($key);
		return $ret;
	}
	
	/**
	 * Store a model.
	 */
	public function store($key, $val = NULL) {
		$this->offsetSet($key, $val);
	}
	
	/**
	 * This is more for semantic meaning than actual practical use.
	 */
	public function create($key, AbstractModel $model) {		
		parent::offsetSet($key, $model);
	}
}
