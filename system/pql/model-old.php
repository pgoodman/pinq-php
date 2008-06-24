<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists('struct')) {
	
	/**
	 * The name is optional, however, it implies that we're using a named
	 * resource. For example, a database table is a named resource. Regardless
	 * of if a name is added or not, all resources will be aliased.
	 * @author Peter Goodman
	 */
	function &struct($name = NULL) {
		$model = new Model($name);
		return $model;
	}
	
	/**
	 * Define a through relationship. This is simply syntactic sugar for an
	 * array that gives more semantic meaning to relationships.
	 * @author Peter Goodman
	 */
	function through() {
		return func_get_args();
	}
}

/**
 * A model: an description of a hypothetical data structure.
 * @author Peter Goodman
 */
class Model {
	
	// field types
	const TYPE_UNKNOWN = 0,
	      TYPE_INT = 1,
	      TYPE_FLOAT = 2,
	      TYPE_DOUBLE = 2,
	      TYPE_STRING = 4,
	      TYPE_ENUM = 8,
	      TYPE_BINARY = 16,
	      TYPE_BOOLEAN = 32,
	      TYPE_MODEL = 64; // for inner models
	
	// internal model stuff
	protected $_contexts = array(),
	          $_context,
	          $_name;
	
	// for relating one model to another
	public $_relations = array(),
	       $_mappings = array(),
	       $_cached_paths = array();
	
	/**
	 * Constructor, bring in an option "absolute" name.
	 */
	public function __construct($name = NULL) {
		$this->_name = $name;
	}
	
	/**
	 * Destructor
	 */
	public function __destruct() {
		unset(
			$this->_context,
			$this->_contexts,
			$this->_relations,
			$this->_mappings,
			$this->_cached_paths
		);
	}
	
	public function setName($name) {
		$this->_name = $name;
	}
	
	public function getName() {
		return $this->_name;
	}
	
	public function hasField($field) {
		return isset($this->_contexts[$field]);
	}
	
	public function getFields() {
		return $this->_contexts;
	}
	
	/**
	 * Given a field name and a value, coerce the value to the specific tyoe
	 * as defined in the model.
	 */
	public function coerceValueForField($field, $value) {
		
		// this model doesn't contain the supplied field
		if(!$this->hasField($field))
			return NULL;
		
		$context = $this->_contexts[$field];
		
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
			
			case self::TYPE_BINARY:
				return base64_encode($value);
			
			default:
				throw new InvalidArgumentException(
					"Model::coerceValueForField() cannot cast a value of an ".
					"unknown or complex type."
				);
		}
		
		return NULL;
	}
	
	/**
	 * Define a field that this model has. Note: fields can be defined
	 * multiple times, where every subsequent definition simply modifies the
	 * original definition.
	 */
	public function __get($field) {
		
		if(!$this->hasField($field)) {
			$this->_contexts[$field] = array(
				'name' => $field,
				'type' => self::TYPE_UNKNOWN,
				'value' => NULL,
				'length' => 0,
				'extra' => array(),
			);
		}
		$this->_context = &$this->_contexts[$field];
		
		return $this;
	}
	
	/**
	 * Require that a context be set.
	 * @internal
	 */
	protected function requireContext() {
		if(NULL === $this->_context) {
			throw new BadMethodCallException(
				"Cannot call method in Model without first specifying the ".
				"field it applies to."
			);
		}
	}
	
	/**
	 * Function for setting a generic type.
	 * @internal
	 */
	protected function setType($type, $length = 0, $value = NULL) {
		$this->requireContext();		
		$this->_context['type'] = $type;
		$this->_context['length'] = $length;
		$this->_context['value'] = $value;
		
		return $this;
	}
	
	/**
	 * Types.
	 */
	public function int($length) {
		return $this->setType(self::TYPE_INT, $length);
	}
	
	public function string($length = 0) {
		return $this->setType(self::TYPE_STRING, $length);
	}
	
	public function bool() {
		return $this->setType(self::TYPE_BOOLEAN);
	}
	
	public function float($length) {
		return $this->setType(self::TYPE_FLOAT, $length);
	}
	
	public function enum() {
		$args = func_get_args();
		return $this->setType(self::TYPE_ENUM, 0, $args);
	}
	
	public function nested(Model $inner) {
		return $this->setType(self::TYPE_MODEL, 0, $inner);
	}
	
	/**
	 * Extras
	 */
	public function __call($key, array $args = array()) {
		$this->requireContext();
		$this->_context['extra'][$key] = $args;
		
		return $this;
	}
	
	/**
	 * Define a relationship between this model and one or more others. Models
	 * can be related through other models. How many things it relates to is
	 * not defined because the structure of the model and how queries being
	 * made on it should reflect this.
	 */
	public function relateTo($model_alias, array $through = NULL) {
		return $this->relatesTo($model_alias, $through);
	}
	
	public function relatesTo($model_alias, array $through = NULL) {
		
		// add the relationship
		$how = !empty($through) ? ModelRelations::INDIRECT
		                        : ModelRelations::DIRECT;
		
		$this->_relations[$model_alias] = array($how, $through);
		
		// if we're going through other models, then this table is
		// directly related to the first model in the $through array.
		// we assume the same type of relationship.
		if($how === ModelRelations::INDIRECT) {
			$model_alias = strtolower($through[0]);
			
			// the first item in the $through might actually be indirect. if
			// it's already in the relations then we just won't set it. if it
			// isn't then we'll assume direct
			if(!isset($this->_relations[$model_alias])) {
				
				$this->_relations[$model_alias] = array(
					ModelRelations::DIRECT, 
					NULL
				);
			}
		}
		
		return $this;
	}
	
	/**
	 * Map a property in this model to a hypothetical property in another
	 * model. Note: foreign property can be a property in the current model.
	 * Note, a map is by definition a two-way street, meaning if we try to
	 * connect the foreign model to this model without defining a mapping in
	 * the foreign model, we will search this model for the appropriate
	 * mapping.
	 */
	public function mapsTo($model_alias, $foreign_field) {
		return $this->mapTo($model_alias, $foreign_field);
	}
	
	public function mapTo($model_alias, $foreign_field) {
		$this->requireContext();
		
		// model aliases are case insensitive
		$model_alias = strtolower($model_alias);
		
		// add this mapping array if it doesn't yet exist
		if(!isset($this->_mappings[$model_alias]))
			$this->_mappings[$model_alias] = array();
		
		// create the mapping between the property of a foreign model to a
		// property of this model. assumption: only one mapping from one
		// table to another.
		$this->_mappings[$model_alias] = array(
			$foreign_field, 
			$this->_context['name']
		);
		
		// add in a direct relationship
		$this->relatesTo($model_alias, NULL);
				
		return $this;
	}
}
