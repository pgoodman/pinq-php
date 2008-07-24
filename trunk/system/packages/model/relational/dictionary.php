<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

require_once dirname(__FILE__) .'/../dictionary.php';

/**
 * Dictionary for model definitions that are relational.
 *
 * @author Peter Goodman
 */
class PinqModelRelationalDictionary extends PinqModelDictionary {
	
	protected $_relations;
	
	/**
	 * PinqModelDictionary(string $dir, PinqModelRelationalMap)
	 *
	 * Bring in an instance of the PinqModelRelationalMap class to manage all data-
	 * souce/model specific relations and an absolute path to the directory
	 * where model definition files are located.
	 */
	public function __construct($dir, PinqModelRelationalMap $relations) {
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
	 * -> PinqModelRelationalDefinition
	 *
	 * Get the relational model definition.
	 *
	 * @internal
	 */
	protected function getDefinition($model_name, $class) {

		$definition = new $class($model_name, $this->_relations);
		
		if(!($definition instanceof PinqModelRelationalDefinition)) {
			throw new DomainException(
				"Class [{$class}] must be an instance of ".
				"PinqModelRelationalDefinition."
			);
		}
		
		return $definition;
	}
}
