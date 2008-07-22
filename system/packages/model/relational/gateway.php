<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

require_once dirname(__FILE__) .'/../gateway.php';

/**
 * Class for handling relational models. The relational model gateway is
 * coupled to the PQL classes because they are the only way to express
 * relations between models when querying the data source.
 *
 * @author Peter Goodman
 */
class PinqModelRelationalGateway extends PinqModelGateway {
	
	protected $_relations,
	          $_compiler;
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();		
		unset($this->_relations);
	}
	
	/**
	 * $g->setRelations(PinqModelRelationalManager) -> void
	 *
	 * Set the relations dictionary for this gateway.
	 */
	public function setRelations(PinqModelRelationalManager $relations) {
		$this->_relations = $relations;
	}
	
	/**
	 * @see GatewayGateway::createGateway(...)
	 */
	protected function createGateway($gateway_name) {
		$gateway = parent::createGateway($gateway_name);
		$gateway->setRelations($this->_relations);
		
		return $gateway;
	}
	
	/**
	 * $g->createPqlQuery(void) -> void
	 *
	 * Create a PQL query for this named gateway.
	 */
	public function createPqlQuery() {
				
		// set the partial query to this query predicates object
		if(NULL === ($gateway_name = $this->getName()))
			return new Query;
				
		$data = $this->getData();
		return from($gateway_name)->select(
			empty($data) ? ALL : $data
		);
	}
	
	/**
	 * $g->getPqlQueryCompiler(void) -> QueryCompiler
	 *
	 * Get the data-source specific query compiler for PQL queries handled by
	 * this gateway.
	 */
	public function getPqlQueryCompiler() {
		
		if($this->_compiler === NULL) {
			$this->_compiler = $this->_data_source->getQueryCompiler(
				$this->_model_dict,
				$this->_relations
			);
		}
		
		return $this->_compiler;
	}
	
	/**
	 * $g->selectByPredicates(string field, mixed $value) -> QueryPredicates
	 *
	 * Create the predicates needed to find one or more rows from the data 
	 * source given a field a what value it should have. The value passed in
	 * to $value must be scalar and cannot be a substitute value (_).
	 *
	 * @internal
	 */
	protected function selectByPredicates($field, $value) {
		
		$field = (string)$field;
		
		// make sure a substitute value isn't being passed in
		if(_ === $value) {
			throw new InvalidArgumentException(
				"ModelGateway::find[All]By() does not accept a substitute ".
				"value for the value of a field."
			);
		}
		
		$model_name = $this->getName();
		$definition = $this->_model_dict[$model_name];
		
		// make sure the field actually exists
		if(!($definition instanceof PinqModelDefinition) || !$definition->hasField($field)) {
			throw new InvalidArgumentException(
				"ModelGateway::get[All]By() expects first argument to be an ".
				"existing field in model definition [{$model_name}]."
			);
		}
		
		// create the PQL query and coerce the value that's going into the
		// query
		return where()->{$model_name}($field)->eq(
			$definition->coerceValueForField($field, $value)
		);
	}
	
	/**
	 * $m->selectBy(string $field, mixed $value) -> Record
	 * 
	 * Get a single record from the data source where the record's field=value.
	 *
	 * @see ModelGateway::get(...)
	 */
	public function selectBy($field, $value) {
		return $this->select(
			$this->selectByPredicates($field, $value)
		);
	}
	
	/**
	 * $g->selectAllBy(string $field, mixed $value) -> RecordIterator
	 *
	 * Get many records from the data source where each record's field=value.
	 *
	 * @see ModelGateway::getAll(...)
	 */
	public function selectAllBy($field, $value) {
		if(NULL === $this->_partial_query)
			return NULL;
		
		return $this->selectAll(
			$this->selectByPredicates($field, $value)
		);
	}
}