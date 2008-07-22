<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

require_once dirname(__FILE__) .'/../definition.php';

/**
 * Class representing a model that relates to other models.
 *
 * @author Peter Goodman
 */
abstract class PinqModelRelationalDefinition extends PinqModelDefinition {
	
	protected $_relations;
	
	/**
	 * PinqModelRelationalDefinition(string $name, PinqModelRelationalManager)
	 */
	public function __construct($name, PinqModelRelationalManager $relations) {
		
		// relations stuff
		$this->_relations = $relations;
		$relations->registerModel($name);
		
		parent::__construct($name);
	}

	/**
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->_relations);
	}
	
	/**
	 * $d->mapsTo(string $model_alias, string $foreign_field) -> PinqModelDefinition
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
				"PinqModelDefinition::mapsTo() can only be called after ".
				"PinqModelDefinition::__get()."
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
	 * $d->relatesTo(string $model_alias[, array $through]) -> PinqModelDefinition
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
}
