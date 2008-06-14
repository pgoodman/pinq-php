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
		unset($this->ds);
	}
	
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
				
		echo "{$query}\n";
		
		return (string)$query;
	}
	
	/**
	 * Find a single record from a data source. This function accepts a string
	 * query or an abstract query object. It also takes arguments to substitute
	 * into the query.
	 */
	public function find($query, array $args = array()) {
		$query = $this->getQuery($query, ConcreteQuery::SELECT);
		$result = $this->ds->select($query, $args);
		
		if(count($result) === 0)
			return NULL;
		
		return $result->current();
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
