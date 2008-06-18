<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class ModelGateway extends RecordGateway {
	
	// an instance of AbstractQuery
	protected $partial_query,
	          $model_name,
	          $cached_relations = array();
	
	/**
	 * Destructor, clear things up.
	 */
	public function __destruct() {
		unset($this->partial_query);
		parent::__destruct();
	}
	
	/**
	 * TODO
	 */
	public function __get($key) {
		assert(FALSE);
	}
	
	/**
	 * Set the name of this gateway.
	 */
	public function setName($name) {
		$this->model_name = $name;
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
			$query->setPredicates($predicates);
				
		// compile the query
		$query = $this->compileQuery($query, $type);
		
		// a string was passed instead of a predicates object
		if(is_string($predicates))
			$query .= " {$predicates}";
		
		echo "{$query}\n\n";
		
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
	 * Find by query or by record.
	 */
	public function findAll($query, array $args = array()) {
		
		// if a record is being passed in then we need to build up
		if($query instanceof Record) {
			
			$record = $query;
			
			// go find the *real* record that we're using
			while($record instanceof OuterRecord)
				$record = $record->getInnerIterator();
			
			// we can't work with an ambiguous record
			if(!$record->isNamed()) {
				throw new DomainException(
					"Cannot build relationship off of ambiguous record. If ".
					"this record has sub-records (ie: the PQL query selected ".
					"from multiple models at once) then the relationship ".
					"needs to be called on one of the sub records. If this ".
					"record was not found through PQL then you cannot use ".
					"this feature."
				);
			}
			
			$record_name = $record->getName();
			
			// if we haven't already created a query for this relation, then
			// do so. the reason why these types of relations are cached is
			// because if they are called on in a loop then this function
			// would otherwise be very slow
			if(!isset($this->cached_relations[$record_name])) {
								
				// clone it so that we can use it again if necessary
				$query = clone $this->partial_query;
				
				$query->from($record_name)->link(
					$this->model_name, 
					$record_name, 
					AbstractQuery::PIVOT_RIGHT
				);
			
				// cache the compiled query, note that uses record gateway's
				// compile query method
				$this->cached_relations[$record_name] = parent::getQuery(
					$query, 
					ConcreteQuery::SELECT
				);
			}
			
			$query = $this->cached_relations[$record_name];
		}
		
		return parent::findAll($query, $args);
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
