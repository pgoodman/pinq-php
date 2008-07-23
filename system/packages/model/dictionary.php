<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Manages all models for a specific data source (withough knowing what that
 * data source is). This class also manages all relations between the models
 * through an instance of the PinqModelRelationalManager class. 
 *
 * @author Peter Goodman
 */
class PinqModelDictionary extends Dictionary implements InstantiablePackage {
	
	protected $_model_dir;
	
	/**
	 * PinqModelDictionary(string $dir) ! InvalidArgumentException
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
	 * $d->offsetGet(string $model_name) <==> $d[$model_name] -> PinqModelDefinition
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
		$file_name = "{$this->_model_dir}/{$file}.php";
		
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
		$definition = TRUE;
		
		// if there is a model definition then instanciate it and load up its
		// fields.
		if(class_exists($class, FALSE)) {
			$definition = $this->getDefinition($model_name, $class);
			$definition->describe();
		}
		
		// store it
		$this->offsetSet($model_name, $definition);
		
		return parent::offsetGet($model_name);
	}
	
	protected function getDefinition($model_name, $class) {
		$definition = new $class($model_name);
		
		if(!($definition instanceof PinqModelDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be an instance of PinqModelDefinition."
			);
		}
		
		return $definition;
	}
}
