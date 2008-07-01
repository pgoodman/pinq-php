<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class describing what fields a model has. The methods of this class allow
 * the programmer to describe and interact with a hypothetical data structure.
 *
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
	
	private $_fields = array(), // fields in this model
	        $_context, // what field we're currently working with
	        $_relations, // ModelRelations instance
	
	         // the name that is used to identify this model in the code
	        $_external_name,
	        
	        // starts off as the externam name, but, for example with
	        // databases, the internal name would be the table name, the
	        // external name would be the file name sans extension
	        $_internal_name,
	        
	        // cached version of external name run through class_name()
	        $_extenal_name_as_class;
	
	/**
	 * ModelDefinition(string $name, ModelRelations)
	 *
	 * Brings in the external name (how this model will be referred to in the
	 * code) and a ModelRelations instance to specify how this model relates
	 * to all others.
	 *
	 * @note By default the external and internal names are the same. To change
	 *       the internal name one must call ModelDefinition::setInternalName().
	 */
	final public function __construct($name, ModelRelations $relations) {
		
		// naming things
		$this->_extenal_name_as_class = class_name($name);
		$this->_external_name = $this->_internal_name = strtolower($name);
		
		// relations stuff
		$this->_relations = $relations;
		$relations->registerModel($name);
		
		// hook
		$this->__init__();
	}

	/**
	 */
	public function __destruct() {
		$this->__del__();
		unset(
			$this->_relations,
			$this->_fields
		);
	}
	
	/**
	 * $d->describe(void) -> void
	 *
	 * Describe the fields and relations that this model has.
	 */
	abstract public function describe();
	
	/**
	 * $d->validateFields(array $fields, int $query_type) -> array
	 *
	 * Perform validating/filtering on data to be stored to a data source. By
	 * default this method lets only fields existing in this model through and
	 * does not type coercion or validation.
	 *
	 * @note To report validation errors, throw a FailedValidationException.
	 * @note Type coercion is automatically done after field validation.
	 */
	public function validateFields(array &$fields, $query_type) {
		return array_intersect_key($fields, $this->_fields);
	}
	
	/**
	 * $d->__init__(void) -> void
	 *
	 * A hook called right after class construction.
	 */
	protected function __init__() { }
	
	/**
	 * $d->__del__(void) -> void
	 *
	 * A hook called before resources are destroyed.
	 */
	protected function __del__() { }
	
	/**
	 * $d->setInternalName(string) -> void
	 *
	 * Change the internal name of this model. By default the internal name is
	 * the same as the external name.
	 */
	final public function setInternalName($name) {
		$this->_internal_name = $name;
	}
	
	/**
	 * $d->getExternalName(void) -> string
	 *
	 * Get the external name of this model.
	 */
	final public function getExternalName() {
		return $this->_external_name;
	}
	
	/**
	 * $d->getInternalName(void) -> string
	 *
	 * Get the internal name of this model. By default the internal model name
	 * is the external name of the model.
	 */
	final public function getInternalName() {
		return $this->_internal_name;
	}
	
	/**
	 * $d->getFields(void) -> array
	 *
	 * Return a numerically indexed array of the fields in this model.
	 */
	final public function getFields() {
		return array_keys($this->_fields);
	}
	
	/**
	 * $d->__set(string $key, array $type) <==> $d->$key = $type -> void
	 *
	 * Set a field to the model. The field type is a numerically indexed array
	 * with three elements. The first is integer type of the field, the second
	 * is the integer length of the field (defaults to 0), and the third is
	 * the default value (defaults to NULL).
	 */
	public function __set($key, $type) {
		
		$key = (string)$key;
		PINQ_DEBUG && assert('is_array($type) && count($type) === 3');
		
		$this->_fields[$key] = array(
			'name' => $key,
			'type' => (int)$type[0],
			'length' => (int)$type[1],
			'value' => $type[2],
		);
	}
	
	/**
	 * $d->__get(string $key) <==> $d->$key -> ModelDefinition
	 *
	 * Set up a context so that we can map fields from other models on to this
	 * one. This returns the current instance. If the field supplied does not
	 * exist then a InvalidArgumentException is thrown.
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
	
	/**
	 * Unsupported
	 */
	public function __isset($key) { }
	
	/**
	 * Unsupported
	 */
	public function __unset($key) { }
	
	/**
	 * $d->mapsTo(string $model_alias, string $foreign_field) -> ModelDefinition
	 *
	 * This function must be called after a call to __get() such that a field
	 * context is set. With that field context, this method adds in a field
	 * mapping and direct relationship between this model and $model_alias.
	 * The field mapping maps the current model and field context to the foreign
	 * model's field ($foreign_field).
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
	 * $d->relatesTo(string $model_alias[, array $through]) -> ModelDefinition
	 *
	 * Relate this model to another one, possibly through intermediate models.
	 * The intermediate $through models is an array of external model names.
	 * The model names need to be in order but they do not need to be a direct
	 * path. As long as relationships exist between the models in the through
	 * array the path can be satisfied. If the $through array is not supplied,
	 * ie: it's empty, then a direct relationship will be created between this
	 * model and $model_alias.
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
	 * $d->hasField(string) -> bool
	 *
	 * Check if a field exists (by its field name) in this model.
	 */
	public function hasField($field) {
		return isset($this->_fields[$field]);
	}
	
	/**
	 * $d->coerceValueForField(string $name, mixed $value) -> mixed
	 *
	 * Given a field name and a value, coerce the value to the specific tyoe
	 * as defined in the model. If the field does not exist in the model then
	 * NULL is returned.
	 */
	public function coerceValueForField($field, $value) {
		
		$field = (string)$field;
		
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
	 * $d->getDefaultGatewayClass(void) -> string
	 */
	abstract protected function getDefaultGatewayClass();
	
	/**
	 * $d->getDefaultRecordClass(void) -> string
	 */
	abstract protected function getDefaultRecordClass();
	
	/**
	 * $d->getGatewayClass(void) -> string
	 *
	 * Get the class name of the model gateway that will be used to query for
	 * records. This method first tries to find model-specific gateway, 
	 * identified by the mixed-case version of the external model name with
	 * 'Gateway' appended on the end. For example:
	 *     model_name -> ModelNameGateway
	 * If a custom model gateway class does not exist then this method will
	 * use the gateway class name returned from getDefaultGatewayClass().
	 * @internal
	 */
	final public function getGatewayClass() {
		$class = $this->_extenal_name_as_class .'Gateway';
		return class_exists($class, FALSE) 
		       ? $class 
		       : $this->getDefaultGatewayClass();
	}
	
	/**
	 * $d->getRecordClass(void) -> string
	 *
	 * Get the class name of the record that will be used to represent 
	 * individual result rows from a query on the data source. This method 
	 * first tries to find model-specific record class, identified by the 
	 * mixed-case version of the external model name with 'Record' appended 
	 * on the end. For example:
	 *     model_name -> ModelNameRecord
	 * If a custom record class does not exist then this method will use the 
	 * record class name returned from getDefaultRecordClass().
	 *
	 * @internal
	 */
	final public function getRecordClass() {
		$class = $this->_extenal_name_as_class .'Record';
		return class_exists($class, FALSE) 
		       ? $class 
		       : $this->getDefaultRecordClass();
	}
}
