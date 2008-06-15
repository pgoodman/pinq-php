<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A gateway to the records in a given data source.
 * @author Peter Goodman
 */
abstract class RecordGateway {
	
	// the data source
	protected $ds,
	          $models;
	
	// quick links to concrete-query's methods
	protected static $types = array(
		ConcreteQuery::SELECT => 'compileSelect',
		ConcreteQuery::INSERT => 'compileInsert',
		ConcreteQuery::UPDATE => 'compileUpdate',
		ConcreteQuery::DELETE => 'compileDelete',
	);
	
	/**
	 * Constructor, bring in the data source.
	 */
	public function __construct(DataSource $ds, Dictionary $models) {
		$this->ds = $ds;
		$this->models = $models;
	}
	
	/**
	 * Destructor.
	 */
	public function __destruct() {
		unset($this->ds, $this->models);
	}
	
	/**
	 * Given a model name, return a model gateway instance. Using this
	 * function selects all fields from the model.
	 */
	public function __get($model) {
		return $this->__call($model, array(ALL));
	}
	
	/**
	 * Given a model name, return a model gateway that contains an abstract
	 * query that selects all fields in the $args array. This makes it
	 * possible to do $ds->model_name('fields', 'to', 'select').
	 */
	public function __call($model, array $select = array()) {
		// return the model gateway
		if(isset($this->models[$model])) {
			$gateway = $this->getModelGateway();
			
			// build a query for the model
			$query = from($model);

			// apply the select fields
			call_user_func_array(array($query, 'select'), $select);
			
			// set the unfinished query to the gateway. note: the gateway
			// doesn't know anything about the model it's holding. it's only
			// job is to store a query.
			$gateway->setPartialQuery($query);
			
			return $gateway;
		}
		
		// model that was passed in didn't exist
		throw new UnexpectedValueException(
			"A model gateway could not be established to the non-existant ".
			"model [{$model}]."
		);
	}
	
	/**
	 * Return a new instance of a model gateway.
	 */
	abstract protected function getModelGateway();
	
	/**
	 * Compile a query for a specific data source.
	 */
	abstract protected function compileQuery(AbstractQuery $query, $type);
	
	/**
	 * Get a string representation for a datasource-specic query. Even if
	 * abstract query isn't being used, the type can stille be helpful.
	 */
	protected function getQuery($query, $type) {
		
		// the query object actually returns a predicates object at once point
		// so we need to get it out of the predicates object
		if($query instanceof AbstractPredicates)
			$query = $query->getQuery();
		
		// if we were given or derived an abstract query object then we need
		// to compile it.
		if($query instanceof AbstractQuery)
			$query = $this->compileQuery($query, $type);
		
		return (string)$query;
	}
	
	/**
	 * Find a single record from a data source. This function accepts a string
	 * query or an abstract query object. It also takes arguments to substitute
	 * into the query.
	 */
	public function find($query, array $args = array()) {
		
		// add in a limit to the query
		if($query instanceof AbstractPredicates)
			$query->limit(1);
		
		// find all results
		$result = $this->findAll($query, $args);
		
		if(count($result) === 0)
			return NULL;
		
		return $result->current();
	}
	
	/**
	 * Take only the first value from a record. This makes COUNT queries, for
	 * example, very simple.
	 */
	public function findValue($query, array $args = array()) {
		$row = $this->find($query, $args);
		
		// oh well, no row to return
		if(NULL === $row)
			return NULL;
		
		// dig deep into the record if we are dealing with an outer record
		while($row instanceof OuterRecord)
			$row = $row->getInnerRecord();
		
		// we are now likely dealing with a Record that is also a dictionary
		if($row instanceof Dictionary)
			$row = array_values($row->toArray());
		else
			$row = array_values((array)$row);
		
		// does no first element exist?
		if(!isset($row[0]))
			return NULL;
		
		return $row[0];
	}
	
	/**
	 * Find >= one records from the data source. This function accepts a
	 * string query or an abstract query object. It also takes arguments to
	 * substitute into the query.
	 */
	public function findAll($query, array $args = array()) {
		$query = $this->getQuery($query, ConcreteQuery::SELECT);
		return $this->ds->select($query, $args);
	}
	
	/**
	 * Delete records from the data source. This function accepts a string
	 * query, an abstract query object, or a record object (that exists in
	 * the datasource and not just in memory). If a query is being passed then
	 * the arguments array will be substituted into the query.
	 */
	public function delete($what, array $args = array()) {
		
		// deleting based on a query
		if(is_string($what) || $what instanceof AbstractQuery) {
			
			$query = $this->getQuery($query, ConcreteQuery::DELETE);
			return $this->ds->update($query, $args);
			
		// deleting based on a record
		} else if($what instanceof Record) {
			
			// the record is not saved
			if(!$what->isSaved()) {
				throw new UnexpectedValueException(
					"RecordGateway::delete() expected first argument to be ".
					"an existing record. The record passed does not exist ".
					"in its corresponding data source."
				);
			}
			
			// have the record delete itself
			$what->delete();
		}
		
		return FALSE;
	}
}
