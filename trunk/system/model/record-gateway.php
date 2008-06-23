<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A gateway to the records in a given data source.
 * @author Peter Goodman
 */
abstract class RecordGateway {
	
	// the data source and some related stuff
	protected $ds,
	          $models,
	          $cached_gateways = array(),
	          $cached_queries = array();
	
	/**
	 * Constructor, bring in the data source.
	 */
	public function __construct(DataSource $ds, Dictionary $models) {
		$this->ds = $ds;
		$this->models = $models;
	}
	
	/**
	 * Destructor, clear out any references/cached stuff.
	 */
	public function __destruct() {
		unset(
			$this->ds, 
			$this->models,
			$this->cached_gateways,
			$this->cached_queries
		);
	}
	
	/**
	 * Given a model name, return a model gateway instance. Using this
	 * function selects all fields from the model.
	 */
	public function __get($model_name) {
		return $this->__call($model_name, array(ALL));
	}
	
	/**
	 * Given a model name, return a model gateway that contains an abstract
	 * query that selects all fields in the $args array. This makes it
	 * possible to do $ds->model_name('fields', 'to', 'select').
	 */
	public function __call($model_name, array $select = array()) {
		
		// return the model gateway
		if(!isset($this->models[$model_name])) {
			throw new UnexpectedValueException(
				"RecordGateway::__call/__get expected valid model name, ".
				"model [{$model_name}] does not appear to exist."
			);
		}
		
		// this isn't exactly dependable. todo: make this dependable
		$gateway_id = $model_name . count($select);
		
		// return the cached gateway
		if(isset($this->cached_gateways[$gateway_id]))
			return $this->cached_gateways[$gateway_id];
		
		//$gateway = $this->getModelGateway($model_name);
		$model = $this->models->load($model_name);
		$gateway = $model->getModelGateway($this->ds, $this->models);
		$gateway->setName($model_name);
				
		//$gateway->setName($model_name);
		
		// build a query for the model
		$query = from($model_name)->select($select);
		
		// set the unfinished query to the gateway. note: the gateway
		// doesn't know anything about the model it's holding. it's only
		// job is to store a query.
		$gateway->setPartialQuery($query);
		
		// return and cache the gateway
		return $this->cached_gateways[$gateway_id] = $gateway;
	}
	
	/**
	 * Compile a query for a specific data source.
	 */
	abstract protected function compileQuery(Query $query, $type);
	abstract protected function getRecord(array $data);
	abstract protected function getRecordIterator($result);
	
	/**
	 * Get a string representation for a datasource-specic query. Even if
	 * abstract query isn't being used, the type can stille be helpful.
	 */
	protected function getQuery($query, $type) {
				
		if(is_string($query))
			return $query;
				
		// the query object actually returns a predicates object at once point
		// so we need to get it out of the predicates object
		if($query instanceof QueryPredicates) {
			$query = $query->getQuery();
			
			if(NULL === $query) {
				throw new InvalidArgumentException(
					"Argument passed to RecordGateway method is not string ".
					"PQL query."
				);
			}
		}
				
		// if we were given or derived an abstract query object then we need
		// to compile it.
		if($query instanceof Query) {
			
			// the query has already been compiled and cached, use it
			if(isset($this->cached_queries[$query->_id]))
				return $this->cached_queries[$query->_id];
			
			// nope, we need to compile the query
			$query = $this->cached_queries[$query->_id] = $this->compileQuery(
				$query, 
				$type
			);
		}
						
		return $query;
	}
	
	/**
	 * Query the datasource.
	 */
	protected function selectResult($query, array $args = array()) {
		// compile the query
		if($query instanceof Query || $query instanceof QueryPredicates)
			$query = $this->getQuery($query, QueryCompiler::SELECT);
		
		if(!is_string($query)) {
			throw new UnexpectedValueException(
				"RecordGateway::find[All]() expected either PQL or string ".
				"query."
			);
		}
		
		return $this->ds->select($query, $args);
	}
	
	/**
	 * Find a single record from a data source. This function accepts a string
	 * query or an abstract query object. It also takes arguments to substitute
	 * into the query.
	 */
	public function find($query, array $args = array()) {
		
		// add in a limit to the query to speed up the query given that we
		// are actually going through findAll
		if($query instanceof QueryPredicates)
			$query->limit(1);
		
		// find all results
		$result = $this->selectResult($query, $args);
		
		if(!$result)
			return NULL;
		
		return $this->getRecord($result);
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
		
		// we are now likely dealing with a InnerRecord that is also a dictionary
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
		$result = $this->selectResult($query, $args);
		
		if(!$result)
			return NULL;
		
		return $this->getRecordIterator($result);
	}
	
	/**
	 * Delete records from the data source. This function accepts a string
	 * query, an abstract query object, or a record object (that exists in
	 * the datasource and not just in memory). If a query is being passed then
	 * the arguments array will be substituted into the query.
	 */
	public function delete($what, array $args = array()) {
		
		// deleting based on a query
		if($what instanceof Query)
			$query = $this->getQuery($query, QueryCompiler::DELETE);
			
		// deleting based on a record. this is a bit sketchy as we're going to
		// need to pivot on something. usually, for example with a database,
		// the primary key would be used, but we don't know what primary keys
		// are, but we can make a decent guess of it by using all integer
		// fields
		else if($what instanceof Record) {
			
			// the record is not named, ie: we cannot identify which model
			// is having row(s) deleted from it.
			if(!$what->isNamed()) {
				throw new UnexpectedValueException(
					"RecordGateway::delete() expected first argument to be ".
					"an unambiguous record."
				);
			}
			
			// have the record delete itself
			die('TODO');
			
		} else
			$query = (string)$what;
		
		return $this->ds->update($query, $args);
	}
	
	/**
	 * Create a new record and return the created record. This accepts a
	 * named record, a PQL query, or a SQL query.
	 */
	public function create($query, array $args = array(), $return = TRUE) {
		
		// compile the query
		if($query instanceof Query || $query instanceof QueryPredicates)
			$query = $this->getQuery($query, QueryCompiler::CREATE);
		
		$results = array();
		
		// compiling the query might return multiple queries if we are working
		// with multiple tabls in a pql query
		if(!is_array($query))
			$query = array($query);
		
		// TODO: the $args passed in might be ambiguous, ie: the query would
		//       yield unwanted results if more than one query are compiled.
		foreach($query as $stmt)
			$results[] = $this->ds->update($stmt, $args);
		
		return count($results) == 1 ? array_pop($results) : $results;
	}
	
	/**
	 * Update a record and return the updated record. This accepts a named
	 * record, a PQL query, or a SQL query.
	 */
	public function modify($query, array $args = array()) {
		$query = $this->getQuery($query, QueryCompiler::MODIFY);
		return $this->ds->update($query);
	}
}
