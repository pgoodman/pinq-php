<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A model definition.
 * @author Peter Goodman
 */
abstract class ModelDefinition implements Object {
	
	// field types
	const TYPE_UNKNOWN = 0,
	      TYPE_INT = 1,
	      TYPE_FLOAT = 2,
	      TYPE_DOUBLE = 2,
	      TYPE_STRING = 4,
	      TYPE_ENUM = 8,
	      TYPE_BINARY = 16,
	      TYPE_BOOLEAN = 32;
	
	private $_fields = array(),
	        $_context,
	        $_relations,
	
	         // the name that is used to identify this model in the code
	        $_external_name,
	        
	        // starts off as the externam name, but, for example with
	        // databases, the internal name would be the table name, the
	        // external name would be the file name sans extension
	        $_internal_name,
	        
	        // cached version of external name run through class_name()
	        $_extenal_name_as_class;
	
	/**
	 * Constructor, bring in the externa name of this model and the relations
	 * object.
	 * @internal
	 */
	final public function __construct($name, ModelRelations $relations) {
		
		// naming things
		$this->_extenal_name_as_class = class_name($name);
		$this->_external_name = $this->_internal_name = strtolower($name);
		
		// relations stuff
		$this->_relations = $relations;
		$relations->registerModel($name);
		
		// hook
		$this->initialize();
	}

	/**
	 * Destructor, obvious.
	 */
	public function __destruct() {
		unset(
			$this->_relations,
			$this->_fields
		);
	}
	
	/**
	 * Describe the model.
	 */
	abstract public function describe();
	
	/**
	 * A would-be abstract function for validating data. By default all data
	 * will pass validation. Note that validation is a distinct step from
	 * coercing values to a specific type.
	 */
	public function validateFields(array $fields) {
		return $fields;
	}
	
	/**
	 * A hook for model initialization.
	 */
	public function initialize() {
		// moo
	}
	
	/**
	 * Set a different internal name.
	 */
	final public function setInternalName($name) {
		$this->_internal_name = $name;
	}
	
	/**
	 * Get the external name.
	 */
	final public function getExternalName() {
		return $this->_external_name;
	}
	
	/**
	 * Get the internal name.
	 */
	final public function getInternalName() {
		return $this->_internal_name;
	}
	
	/**
	 * Return an array of the fields in this model.
	 */
	final public function getFields() {
		return array_keys($this->_fields);
	}
	
	/**
	 * Set a field to the model.
	 */
	public function __set($key, $type) {
		
		$this->_fields[$key] = array(
			'name' => $key,
			'type' => $type[0],
			'length' => $type[1],
			'value' => $type[2],
		);
	}
	
	/**
	 * Set up a context so that we can map fields from other models on to this
	 * one.
	 */
	public function __get($key) {
		if(!isset($this->_fields[$key])) {
			throw new InvalidArgumentException(
				"The model field [{$key}] does not exist (yet)."
			);
		}
		
		$this->_context = $key;
		return $this;
	}
	
	public function __isset($key) { }
	public function __unset($key) { }
	
	/**
	 * Map a context (field) to a field in another model definition.
	 */
	public function mapsTo($model_alias, $foreign_field) {
		
		if(NULL === $this->_context) {
			throw new InvalidMethodCallException(
				"ModelDefinition::mapsTo() can only be called after ".
				"ModelDefinition::__get()."
			);
		}
		
		// add a mapping (this also adds a direct relationship)
		$this->_relations->addMapping(
			$this->_external_name, $this->_context,
			strtolower($model_alias), $foreign_field
		);
		
		return $this;
	}
	
	/**
	 * Relate this model to another one.
	 */
	public function relatesTo($model_alias, array $through = array()) {
		$this->_relations->addRelation(
			$this->_external_name,
			strtolower($model_alias),
			$through
		);
		
		return $this;
	}
	
	/**
	 * Check if this model has a field.
	 */
	public function hasField($field) {
		return isset($this->_fields[$field]);
	}
	
	/**
	 * Given a field name and a value, coerce the value to the specific tyoe
	 * as defined in the model.
	 */
	public function coerceValueForField($field, $value) {
		
		// this model doesn't contain the supplied field
		if(!$this->hasField($field) || NULL === $value)
			return NULL;
		
		$context = $this->_fields[$field];
		
		switch($context['type']) {
			
			case self::TYPE_INT:				
				return (int)$value;
			
			case self::TYPE_BOOLEAN:
				return (int)(bool)$value;
			
			case self::TYPE_FLOAT:
				return (float)$value;
			
			case self::TYPE_STRING:
				$ret = (string)$value;
				if($context['length'] > 0)
					$ret = substr($ret, 0, $context['length']);
				
				return $ret;
			
			case self::TYPE_ENUM:
				return in_array($value, $context['value']) ? $value : NULL;
			
			// TODO: this probably isn't what should be done
			case self::TYPE_BINARY:
				return base64_encode($value);
			
			default:
				throw new InvalidArgumentException(
					"ModelDefinition::coerceValueForField() cannot cast a ".
					"value of an unknown or complex type."
				);
		}
		
		return NULL;
	}
	
	/**
	 * These specify the default classes for gateways, records, and iterators.
	 */
	abstract protected function getDefaultGatewayClass();
	abstract protected function getDefaultRecordClass();
	
	/**
	 * Get the class name for a record.
	 * @internal
	 */
	final public function getGatewayClass() {
		$class = $this->_extenal_name_as_class .'Gateway';
		return class_exists($class, FALSE) 
		       ? $class 
		       : $this->getDefaultGatewayClass();
	}
	
	/**
	 * Get the class name for a record.
	 * @internal
	 */
	final public function getRecordClass() {
		$class = $this->_extenal_name_as_class .'Record';
		return class_exists($class, FALSE) 
		       ? $class 
		       : $this->getDefaultRecordClass();
	}
}