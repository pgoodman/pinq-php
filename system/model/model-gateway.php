<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class ModelGateway extends RecordGateway {
	
	// an instance of AbstractQuery
	protected $partial_query;
	
	/**
	 * Destructor, clear things up.
	 */
	public function __destruct() {
		unset($this->partial_query);
		parent::__destruct();
	}
	
	/**
	 * Get a string representation for a datasource-specic query. Even if
	 * abstract query isn't being used, the type can stille be helpful.
	 */
	protected function getQuery($predicates, $type) {
		
		// clone it so that we can use it again if necessary
		$query = clone $this->partial_query;
		
		// we've got a predicates object and we also have a query object
		// as an instance method
		if($predicates instanceof AbstractPredicates)
			$predicates->setQuery($query);
		
		// compile the query
		$query = $this->compileQuery($query, $type);
		
		// a string was passed instead of a predicates object
		if(is_string($predicates))
			$query .= " {$predicates}";
				
		return (string)$query;
	}
	
	/**
	 * Set this gateway's partial query. This is really only an query on a
	 * specific model that selects all of its fields.
	 */
	public function setPartialQuery(AbstractQuery $query) {
		$this->partial_query = $query;
	}
	
	/**
	 * Check that the value passed is scalar (well, just something that isn't
	 * a substitute value). An array or object can be passed, but they would
	 * likely not be handled well.
	 */
	protected function expectScalar($value) {
		if($value === _) {
			throw new UnexpectedValueException(
				"ModelGateway::find[All]By() does not accept a substitute ".
				"value for the value of a field."
			);
		}
	}
	
	/**
	 * Find a row by a (field, value) pair.
	 */
	public function findBy($field, $value) {
		$this->expectScalar($value);
		return $this->find(where()->eq($field, $value));
	}
	
	/**
	 * Find many rows with a (field,value) pair.
	 */
	public function findAllBy($field, $value) {
		$this->expectScalar($value);
		return $this->findAll(where()->eq($field, $value));
	}
}
