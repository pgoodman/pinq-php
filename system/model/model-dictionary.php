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
	
	protected $_model_dir;
	
	/**
	 * ModelDictionary(string $dir) ! InvalidArgumentException
	 */
	public function __construct($dir) {
		$this->_model_dir = $dir;
		
		if(!is_dir($dir)) {
			throw new InvalidArgumentException(
				"Model directory [{$dir}] does not exist."
			);
		}
	}
	
	/**
	 * $d->offsetGet(string $model_name) <==> $d[$model_name] -> ModelDefinition
	 *
	 * Lazy loads and returns a model definition for a particulary data source.
	 *
	 * @note it uses a model's external name for loading. The model name is
	 *       case insensitive.
	 */
	public function offsetGet($model_name) {
		
		// make sure model names are case insensitive
		$model_name = strtolower($model_name);
		
		// model is already stored, return it
		if($this->offsetExists($model_name))
			return parent::offsetGet($model_name);
		
		// we replace periods because this could be a heirarchical model
		$file = str_replace('.', '/', $model_name);
		$file_name = "{$this->_model_dir}/{$file}". EXT;
		
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
		
		if(!class_exists($class, FALSE)) {
			throw new InvalidArgumentException(
				"Class [{$class}] does not exist."
			);
		}
		
		$definition = $this->getDefinition($model_name, $class);		
		$definition->describe();
		
		// store it
		$this->offsetSet($model_name, $definition);
		
		return parent::offsetGet($model_name);
	}
	
	/**
	 * $d->getDefinition(string $model_name, string $class) -> ModelDefinition
	 *
	 * Get the model definition.
	 *
	 * @internal
	 */
	protected function getDefinition($model_name, $class) {
		$definition = new $class($this->_model_name);
		
		if(!($definition instanceof ModelDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be an instance of ModelDefinition."
			);
		}
		
		return $definition;
	}
}

/**
 * Dictionary for model definitions that are relational.
 *
 * @author Peter Goodman
 */
class RelationalModelDictionary extends ModelDictionary {
	
	protected $_relations;
	
	/**
	 * ModelDictionary(string $dir, ModelRelations)
	 *
	 * Bring in an instance of the ModelRelations class to manage all data-
	 * souce/model specific relations and an absolute path to the directory
	 * where model definition files are located.
	 */
	public function __construct($dir, ModelRelations $relations) {
		$this->_relations = $relations;
		parent::__construct($dir);
	}
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->_relations);
	}
	
	/**
	 * $d->getDefinition(string $model_name, string $class) 
	 * -> RelationalModelDefinition
	 *
	 * Get the relational model definition.
	 *
	 * @internal
	 */
	protected function getDefinition($model_name, $class) {
		
		$definition = new $class($model_name, $this->_relations);
		
		if(!($definition instanceof RelationalModelDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be an instance of ".
				"RelationalModelDefinition."
			);
		}
		
		return $definition;
	}
}
