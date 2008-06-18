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
		$model = new AbstractModel($name);
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
 * A description of a hypothetical data structure. This is similar to a php
 * struct() and in fact we will use the struct() function as a quick way to
 * instantiate and use this class.
 * @author Peter Goodman
 */
class AbstractModel extends Stack {
	
	const TYPE_STRING = 1,
		  TYPE_INT = 2,
		  TYPE_DECIMAL = 4,
		  TYPE_BOOL = 8,
		  TYPE_MIXED = 15, // string | int | float | bool
		  TYPE_BINARY = 32,
		  TYPE_MODEL = 64; // for heirarchical structures
	
	// how does this model relate to another? an indirect relationship is one
	// where we will need to traverse one or more other models to get at the
	// disired model
	const RELATE_DIRECT = 1,
		  RELATE_INDIRECT = 2;
	
	// why the underscores in front of the names? even though this is php5,
	// these variables are public and we want to make it less likely for there
	// to be conflicts while using the api provided through this class
	public $_name, // name of this model, not required (for everything)
		   $_properties = array(),
	       $_relations = array(),
		   $_mappings = array(),
		   $_cached_paths = array(); // cached relationship paths built up later
	
	/**
	 * Constructor, bring in the name, if any.
	 */
	public function __construct($name = NULL) {
		$this->_name = $name;
	}
	
	/**
	 * Destructor, clean things up.
	 */
	public function __destruct() {
		unset(
			$this->_properties, 
			$this->_relations, 
			$this->_mappings,
			$this->_cached_paths
		);
	}
	
	/**
	 * Get the name associated with this model.
	 */
	public function getName() {
		return $this->_name;
	}
	
	/**
	 * Create a property and pop any previous ones off the stack.
	 */
	public function __get($property) {
				
		// take everything off the stack
		$this->clear();
		$this->push($property);
		$this->_properties[$property] = array(
			'type' => self::TYPE_MIXED,
			'bytelen' => 0,
			'multibyte' => FALSE, // for multibyte formats, bytelen will be
			'signed' => TRUE,
			'model' => NULL,
			'extra' => array(),   // interpreted differently
		);
		
		return $this;
	}
	
	/**
	 * Set the property byte length if it can to be.
	 */
	protected function setPropertyByteLength(array &$property, array $args) {		
		if(isset($args[0])) {
			
			// add a centinel to the end to make sure the list() works
			$args[] = FALSE;
			
			// if, for some reason, an integer or float is seen as multibyte
			// the multibyte flag will be ignored.
			list($byte_length, $is_multibyte) = $args;
			
			$property['bytelen'] = $byte_length;
			$property['multibyte'] = $is_multibyte;
		}
	}
	
	/**
	 * Cast a value to a property.
	 */
	public function castValue($property, $value) {
		if(!isset($this->_properties[$property]))
			return;
		
		if($value === _)
			return '?';
		
		$property = $this->_properties[$property];
		
		switch($property['type']) {
			case self::TYPE_MIXED:
			case self::TYPE_STRING:
				$value = (string)$value;
				
				if($property['bytelen'])
					$result = substr($value, 0, $property['bytelen']);
				
				$valeu = htmlentities($value, ENT_QUOTES);
				$value = "'{$value}'";
				
				break;
			
			case self::TYPE_INT:
				$value = (int)$value;
				break;
			
			case self::TYPE_DECIMAL:
				$value = (float)$value;
				break;
			
			case self::TYPE_BOOL:
				$value = (int)(bool)$value;
				break;
			
			case self::TYPE_BINARY:
				break;
			
			case self::TYPE_MODEL:
				throw new UnexpectedValueException(
					"Cannot cast type [model] to a scalar value."
				);
				break;
		}
		
		return $value;
	}
	
	/**
	 * Add information to this property.
	 */
	public function __call($key, array $args = array()) {

		$property = &$this->_properties[$this->top()];
		$lower_key = strtolower($key);
		
		switch($lower_key) {
			
			// integers
			case 'int':
			case 'integer':
				$property['type'] = self::TYPE_INT;
				$this->setPropertyByteLength($property, $args);
				break;
			
			// un/signed, signed is somewhat redundant as that is the default
			// case.
			case 'signed':
				$property['signed'] = TRUE;
				break;
			
			case 'unsigned':
				$property['signed'] = FALSE;
				break;
			
			// in the case of a database, string represents both TEXT and
			// VARCHAR. It chooses the appropriate value based on the
			// bytelength. If the bytelength is not supplied the assumption
			// is a TEXT field.
			case 'string':
				$property['type'] = self::TYPE_STRING;
				$this->setPropertyByteLength($property, $args);
				break;
			
			// floating-point numbers with a bytelength are a bit trickier.
			// the bytelength determines the precision of the number.
			case 'float':
			case 'double':
			case 'real':
				$property['type'] = self::TYPE_DECIMAL;
				$this->setPropertyByteLength($property, $args);
				break;
			
			// mixed data type
			case 'mixed':
				$property['type'] = self::TYPE_MIXED;
				$this->setPropertyByteLength($property, $args);
				break;
			
			// binary data
			case 'binary':
				$property['type'] = self::TYPE_BINARY;
				$this->setPropertyByteLength($property, $args);
				break;
			
			// model, this is for heirarchical structures that need nested
			// models
			case 'model':
				$property['type'] = self::TYPE_MODEL;
				$property['model'] = &$args[0];
				break;
			
			// default, assume this is an extra unsupported flag.
			default:
				$property['extra'][$key] = !empty($args) ? $args[0] : TRUE;
				break;
		}
		
		return $this;
	}
	
	/**
	 * Define a relationship between this model and one or more others. Models
	 * can be related through other models. How many things it relates to is
	 * not defined because the structure of the model and how queries being
	 * made on it should reflect this.
	 */
	public function relateTo($alias, array $through = NULL) {
		return $this->relatesTo($alias, $through);
	}
	public function relatesTo($alias, array $through = NULL) {
		
		// add the relationship
		$how = !empty($through) ? AbstractRelation::INDIRECT
		                        : AbstractRelation::DIRECT;
		
		$this->_relations[$alias] = array($how, $through);
		
		// if we're going through other models, then this table is
		// directly related to the first model in the $through array.
		// we assume the same type of relationship.
		if($how & self::RELATE_INDIRECT) {
			$alias = strtolower($through[0]);
			
			// the first item in the $through might actually be indirect. if
			// it's already in the relations then we just won't set it. if it
			// isn't then we'll assume direct
			if(!isset($this->_relations[$alias])) {
				$this->_relations[$alias] = array(
					AbstractRelation::DIRECT, 
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
	public function mapsTo($alias, $foreign_property) {
		return $this->mapTo($alias, $foreign_property);
	}
	public function mapTo($alias, $foreign_property) {
		
		// get the current property on the stack
		$property_name = $this->top();
		
		// model aliases are case insensitive
		$alias = strtolower($alias);
		
		// add this mapping array if it doesn't yet exist
		if(!isset($this->_mappings[$alias]))
			$this->_mappings[$alias] = array();
		
		// create the mapping between the property of a foreign model to a
		// property of this model. assumption: only one mapping from one
		// table to another.
		$this->_mappings[$alias] = array($foreign_property, $property_name);
				
		return $this;
	}
}
