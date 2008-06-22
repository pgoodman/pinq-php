<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class ModelGateway extends RecordGateway {
	
	// an instance of Query
	protected $partial_query,
	          $model_name,
	          $cached_relations = array();
	
	/**
	 * Destructor, clear things up.
	 */	
	public function __destruct() {
		parent::__destruct();
		unset(
			$this->partial_query,
			$this->cached_relations
		);
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
	 * Set this gateway's partial query. This is really only an query on a
	 * specific model that selects all of its fields.
	 */
	public function setPartialQuery(Query $query) {
		$this->partial_query = $query;
	}
	
	/**
	 * Get a cloned version of the partial query.
	 */
	protected function getPartialQuery() {
		return clone $this->partial_query;
	}
	
	/**
	 * Get a string representation for a datasource-specic query. Even if
	 * abstract query isn't being used, the type can stille be helpful.
	 */
	protected function getQuery($predicates, $type) {
		
		$query = $this->getPartialQuery();
		
		// we've got a predicates object and we also have a query object
		// as an instance method
		if($predicates instanceof QueryPredicates) {
			$predicates->setQuery($query);
			$query->setPredicates($predicates);
		}
				
		// compile the query
		$query = $this->compileQuery($query, $type);
				
		// a string was passed instead of a predicates object
		if(is_string($predicates))
			$query .= " {$predicates}";
		
		return $query;
	}
	
	/**
	 * Find by query or by record.
	 */
	public function findAll($query, array $args = array()) {
		
		if(NULL === $query) {
			throw new UnexpectedValueException(
				"ModelGateway::find[All]() expects a PQL predicates query, ".
				"SQL predicates list, or Record instance. NULL given."
			);
		}
		
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
				$query = $this->getPartialQuery();
				
				// add in the predicates to make linking and pivoting to the
				// record possible
				$query->from($record_name)->link(
					$this->model_name, 
					$record_name, 
					Query::PIVOT_RIGHT
				);
			
				// cache the compiled query, note that uses record gateway's
				// compile query method
				$this->cached_relations[$record_name] = parent::getQuery(
					$query, 
					QueryCompiler::SELECT
				);
			}
			
			$args = $record->toArray();
			$query = $this->cached_relations[$record_name];
		}
				
		return parent::findAll($query, $args);
	}
	
	/**
	 * Create the query needed to find one or more rows from the data source 
	 * a field in the model.
	 * @internal
	 */
	protected function createFindByQuery($field, $value) {
		
		// make sure a substitute value isn't being passed in
		if(_ === $value) {
			throw new UnexpectedValueException(
				"ModelGateway::find[All]By() does not accept a substitute ".
				"value for the value of a field."
			);
		}
		
		$model = $this->models[$this->model_name];
		
		// make sure the field actually exists
		if(!$model->hasField($field)) {
			throw new UnexpectedValueException(
				"ModelGateway::findAllBy() expects first argument to be an ".
				"existing field in model [{$this->model_name}]."
			);
		}
		
		// create the PQL query and coerce the value that's going into the
		// query
		return where()->eq($field, $model->coerceValueForField($field, $value));
	}
	
	/**
	 * Find a row by a (field, value) pair.
	 */
	public function findBy($field, $value) {
		return $this->find($this->createFindByQuery($field, $value));
	}
	
	/**
	 * Find many rows with a (field,value) pair.
	 */
	public function findAllBy($field, $value) {
		return $this->findAll($this->createFindByQuery($field, $value));
	}
}
