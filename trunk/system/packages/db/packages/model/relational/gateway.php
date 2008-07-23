<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class for handling relational models. The relational model gateway is
 * coupled to the PQL classes because they are the only way to express
 * relations between models when querying the data source.
 *
 * @author Peter Goodman
 */
class PinqDbModelRelationalGateway extends PinqModelRelationalGateway {
	
	protected $_compiler;
	
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
	 * $g->getPqlQueryCompiler(void) -> PinqPqlQueryCompiler
	 *
	 * Get the data-source specific query compiler for PQL queries handled by
	 * this gateway.
	 */
	public function getPqlQueryCompiler() {
		
		if($this->_compiler === NULL) {
			$this->_compiler = $this->_resource->getPqlQueryCompiler(
				$this->_model_dict,
				$this->_relations
			);
		}
		
		return $this->_compiler;
	}
	
	/**
	 * $g->select(mixed $what[, array $using]) -> {Record, NULL}
	 *
	 * Get a single record from the data source.
	 */
	public function select($what, array $using = array()) {
		$record_iterator = parent::select($what, $using);
		
		if(0 === count($record_iterator))
			return NULL;
				
		$record_iterator->rewind();
		return $record_iterator->current();
	}
	
	/**
	 * $g->getValue(mixed $query[, array $args]) -> {string, int, void}
	 *
	 * Using the record returned from ModelGateway::get(), return the value of
	 * the first selected field or NULL if no record could be found.
	 *
	 * @example
	 *     pql query to get the number of rows in a model:
	 *         $num_records = $g->getValue(
	 *             from('model_name')->count('field_name')
	 *         );
	 */
	public function selectValue($query, array $args = array()) {
		$row = $this->select($query, $args);
		
		// oh well, no row to return
		if(NULL === $row)
			return NULL;

		// dig deep into the record if we are dealing with an outer record
		while($row instanceof OuterRecord)
			$row = $row->getRecord();
		
		// we are now likely dealing with a InnerRecord that is also a dictionary
		if($row instanceof Dictionary)
			$row = $row->toArray();
		
		$row = array_values($row);
		
		// does no first element exist?
		if(!isset($row[0]))
			return NULL;
		
		return $row[0];
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
