<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Decorator for the dictionary that adds semantic meaning to adding models
 * to the dictionary.
 * @author Peter Goodman
 */
class PinqModelLoader extends Loader implements ConfigurablePackage {
	
	protected $dir,
	          $models;
	
	/**
	 * Configure the model loader by having it store an instance of itself in
	 * the package loader.
	 */
	static public function configure(Loader $loader, Loader $config, array $args) {
		
		// make sure the arguments passed into this package from the loader
		// has the information that we expect
		DEBUG_MODE && expect_array_keys($args, array(
			'argv',
			'argc',
			'class',
		));
		
		extract($args);
				
		// the model loader expexts $argv[0] to be a data source type and
		// $argv[1] to be the data source's name
		DEBUG_MODE && assert('$argc >= 2');
				
		$class = $args['class'];
		
		return new $class($argv[0], $argv[1]);
	}
	
	/**
	 * Constructor.
	 */
	final public function __construct($type, $name) {
		$this->dir = "models/{$type}/{$name}";
	}
	
	/**
	 * Lazy-load a model definition.
	 */
	public function load($key, array $context = array()) {
		
		// get the cached version
		if(isset($this->models[$key]))
			return $this->models[$key];
		
		$ret = NULL;
		$file = str_replace('.', '/', $key);
		$file_name = DIR_APPLICATION ."/{$this->dir}/{$file}". EXT;
				
		// no models defined for this key, ignore it (models aren't required)
		if(!file_exists($file_name)) {
			throw new UnexpectedValueException(
				"ModelLoader::load() expects valid model name, model ".
				"[{$key}] does not exist."
			);
		}
		
		require_once $file_name;
		
		// look for the definition file, get the class name and instantiate
		// it with its model name as the only parameter
		$class = class_name($key) .'Definition';
		$model = new $class($key);
		
		if(!($model instanceof ModelDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be a sub-class of ModelDefinition."
			);
		}
				
		// store the description of the model in the dict
		parent::offsetSet($key, $model->describe());
		
		// store the actual model elsewhere
		$this->store($key, $model);
		
		return $model;
	}
	
	/**
	 * Store a model.
	 */
	public function store($key, $val = NULL) {
		$this->models[$key] = $val;
	}
	
	/**
	 * Overwrite the dictionary function for getting a model so that we lazy
	 * load as much as possible.
	 */
	public function offsetGet($model_name) {		
		if(!isset($this->models[$model_name]))
			$this->load($model_name);
		
		return parent::offsetGet($model_name);
	}
}
