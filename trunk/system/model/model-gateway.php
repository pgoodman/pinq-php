<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class ModelGateway extends GatewayGateway {
	
	protected $_data,
	          $_compiler,
	          $_model_dict;
	
	public function setData(array $data) {
		$this->_data = $data;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	public function setModelDictionary(Dictionary $models) {
		$this->_model_dict = $models;
	}
	
	/**
	 * $d->createGateway(void) -> Gateway
	 *
	 * @internal
	 */
	protected function createGateway($name) {		
		$gateway = parent::createGateway($name);
		$gateway->setModelDictionary($this->_model_dict);
		return $gateway;
	}
}

/**
 * Class for handling relational models.
 *
 * @author Peter Goodman
 */
abstract class RelationalModelGateway extends ModelGateway {
	
	protected $_relations;
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();		
		unset($this->_relations);
	}
	
	public function setRelations(ModelRelations $relations) {
		$this->_relations = $relations;
	}
	
	/**
	 */
	protected function createGateway($gateway_name) {
		$gateway = parent::createGateway($gateway_name);
		$gateway->setRelations($this->_relations);
		
		return $gateway;
	}
	
	public function createPqlQuery() {
		
		$gateway_name = $this->getName();
		
		// set the partial query to this query predicates object
		if(NULL === $gateway_name)
			return new Query;
				
		$data = $this->getData();
		return from($gateway_name)->select(
			empty($data) ? ALL : $data
		);
	}
	
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
		if(!($definition instanceof ModelDefinition) || !$definition->hasField($field)) {
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

