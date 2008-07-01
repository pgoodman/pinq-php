<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Manages all models for a specific data source (withough knowing what that
 * data source is). This class also manages all relations between the models
 * through an instance of the ModelRelations class. 
 *
 * @author Peter Goodman
 */
class ModelDictionary extends Dictionary {
	
	private $relations,
	        $model_dir;
	
	/**
	 * ModelDictionary(ModelRelations, string $dir)
	 *
	 * Bring in an instance of the ModelRelations class to manage all data-
	 * souce/model specific relations and an absolute path to the directory
	 * where model definition files are located. If the directory specified
	 * does not exist then an InvalidArgumentException is thrown.
	 */
	public function __construct(ModelRelations $relations, $dir) {
		$this->relations = $relations;
		$this->model_dir = $dir;
		
		if(!is_dir($dir)) {
			throw new InvalidArgumentException(
				"Model directory [{$dir}] does not exist."
			);
		}
	}
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();
		unset(
			$this->relations
		);
	}
	
	public function offsetGet($model_name) {
		
		// make sure model names are case insensitive
		$model_name = strtolower($model_name);
		
		// model is already stored, return it
		if($this->offsetExists($model_name))
			return parent::offsetGet($model_name);
		
		// we replace periods because this could be a heirarchical model
		$file = str_replace('.', '/', $model_name);
		$file_name = "{$this->model_dir}/{$file}". EXT;
		
		// no models defined for this key, ignore it (models aren't required)
		if(!file_exists($file_name)) {
			throw new UnexpectedValueException(
				"ModelDictionary::offsetGet() expects valid model name, ".
				"model [{$model_name}] does not exist."
			);
		}
		
		require_once $file_name;
		
		// look for the definition file, get the class name and instantiate
		// it with its model name as the only parameter
		$class = class_name($model_name) .'Definition';
		
		// ugh.
		if(!class_exists($class, FALSE)) {
			throw new InvalidArgumentException(
				"Class [{$class}] does not exist."
			);
		}
		
		$definition = new $class($model_name, $this->relations);
		$definition->describe();
		
		if(!($definition instanceof ModelDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be an instance of ModelDefinition."
			);
		}
		
		// store it
		$this->offsetSet($model_name, $definition);
		
		return parent::offsetGet($model_name);
	}
}
