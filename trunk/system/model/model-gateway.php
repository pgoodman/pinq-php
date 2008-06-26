<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The model gateway is the link between the model layer and the data sources.
 * It deals with finding and manipulation records in the data source by using
 * the models to fill in the relationships between them and validate data.
 * @author Peter Goodman
 */
abstract class ModelGateway {
	
	protected $_models,
	          $_relations,
	          $_ds,
	          $_partial_query,
	          $_model_name,
	          $_cached_queries = array(),
	          $_cached_relations = array();
	
	/**
	 * Constructor, bring in the models and the data source.
	 */
	public function __construct(ModelDictionary $models, 
		                        ModelRelations $relations,
		                        DataSource $ds, 
		                        $name = NULL) {
		
		$this->_models = $models;
		$this->_ds = $ds;
		$this->_model_name = $name;
		$this->_relations = $relations;
	}
	
	public function __destruct() {
		unset(
			$this->_models,
			$this->_relations,
			$this->_ds,
			$this->_partial_query,
			$this->_cache_queries,
			$this->_cached_relations
		);
	}
	
	/**
	 * Set a partial query.
	 * @internal
	 */
	public function setPartialQuery(Query $query) {
		$this->_partial_query = $query;
	}
	
	/**
	 * Get the partial query. Partial queries are a nice way to allow model
	 * specific gateways.
	 * @internal
	 */
	public function getPartialQuery() {
		if(NULL !== $this->_partial_query)
			return clone $this->_partial_query;
		
		return new Query;
	}
	
	/**
	 * Get a sub-model gateway.
	 */
	public function __get($model_name) {
		return $this->__call($model_name, array(ALL));
	}
	
	/**
	 * Get a sub-model gateway, also selecting specific fields in a PQL query.
	 */
	public function __call($model_name, array $select = array()) {
		
		// if the model doesn't exist then this will throw an exception
		$definition = $this->_models[$model_name];
		$class = $definition->getGatewayClass();
		
		// load up the definition-specific gateway class, or the default one
		$gateway = new $class(
			$this->_models, 
			$this->_relations, 
			$this->_ds, 
			$model_name
		);
		
		$gateway->setPartialQuery(
			$this->getPartialQuery()->from($model_name)->select(ALL)
		);
		
		return $gateway;
	}
	
	/**
	 * Get a string representation of a query.
	 */
	protected function getQuery($query, $type) {
		
		$record_name = NULL;
		
		if($query instanceof Record) {
			
			$record = $query;
			
			// the problem is that we need a partial query to do a query pivot
			// a partial query comes from when use use __call or __get on the
			// model gateway, which returns a new model gateway. If they
			// haven't done this then they are not allowed to pass a record
			// to the query.
			if(NULL === $this->_partial_query) {
				throw new InvalidArgumentException(
					"Cannot pivot on ambiguous model gateway."
				);
			}
			
			// we want to get at the innermost record
			while($query instanceof OuterRecord)
				$query = $query->getInnerRecord();
			
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
			if(!isset($this->_cached_relations[$record_name])) {
												
				// clone it so that we can use it again if necessary
				$query = $this->getPartialQuery();
				
				// add in the predicates to make linking and pivoting to the
				// record possible
				$query->from($record_name)->link(
					$this->_model_name, 
					$record_name, 
					Query::PIVOT_RIGHT
				);
			
			// if the relationship is cached, then the string version of the
			// query exists and this will fall down nicely to the is_string
			// check
			} else
				$query = $this->_cached_relations[$record_name];
		}
		
		// check if the query is a string, if so, return it
		if(is_string($query))
			return $query;
			
		// the query is an object
		else if(is_object($query)) {
			
			// the query object actually returns a predicates object at once
			// point so we need to get it out of the predicates object
			if($query instanceof QueryPredicates) {
			
				// set the partial query to this query predicates object
				if(NULL !== $this->_partial_query)
					$query->setQuery($this->getPartialQuery());
			
				// try to get a query object out of this oredicates object
				if(NULL === ($query = $query->getQuery())) {
					throw new InvalidArgumentException(
						"Argument passed to RecordGateway method is not ".
						"string PQL query."
					);
				}
			}
				
			// if we were given or derived an abstract query object then we
			// need to compile it.
			if($query instanceof Query) {
				
				$query_id = $query->getId();
				
				// the query has already been compiled and cached, use it
				if(isset($this->_cached_queries[$query_id]))
					return $this->_cached_queries[$query_id ];
			
				// nope, we need to compile the query
				$query = $this->compileQuery(
					$query, 
					$type
				);
				
				$this->_cached_queries[$query_id] = $query;
			}
			
			// cache this relation, this is when we're in a sub-model gateway
			// and a Record object is passed to one of the query functions.
			if(NULL !== $record_name)
				$this->_cached_relations[$record_name] = $query;
		}
						
		return $query;
	}
	
	abstract protected function compileQuery(Query $query, $type);
	abstract protected function getRecord($result_resource);
	abstract protected function getRecordIterator($result_resource);
	
	/**
	 * Query the datasource.
	 */
	protected function selectResult($query, array $args = array()) {
		
		if($query instanceof Record)
			$args = $query->toArray();
		
		// get the query, and compile it if necessary
		$query = $this->getQuery($query, QueryCompiler::SELECT);
		
		// we expect a string query back
		if(!is_string($query)) {
			throw new UnexpectedValueException(
				"RecordGateway::find[All,Value]() expected either PQL or ".
				"string query."
			);
		}
		
		return $this->_ds->select($query, $args);
	}
	
	/**
	 * Find a single record from a data source. This function accepts a string
	 * query or an abstract query object. It also takes arguments to substitute
	 * into the query.
	 */
	public function get($query, array $args = array()) {
		
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
	public function getValue($query, array $args = array()) {
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
	public function getAll($query, array $args = array()) {
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
		
		// force it to be a string, oh well :P
		} else
			$query = (string)$what;
		
		return $this->_ds->update($query, $args);
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
		
		$definition = $this->_models[$this->_model_name];
		
		// make sure the field actually exists
		if(!$definition->hasField($field)) {
			throw new UnexpectedValueException(
				"ModelGateway::find[All]By() expects first argument to be an ".
				"existing field in model definition [{$this->_model_name}]."
			);
		}
		
		// create the PQL query and coerce the value that's going into the
		// query
		return where()->$field->eq(
			$definition->coerceValueForField($field, $value)
		);
	}
	
	/**
	 * Find a row by a (field, value) pair.
	 */
	public function getBy($field, $value) {
		if(NULL === $this->_partial_query)
			return NULL;
		
		return $this->find(
			$this->createFindByQuery($field, $value)
		);
	}
	
	/**
	 * Find many rows with a (field,value) pair.
	 */
	public function getAllBy($field, $value) {
		if(NULL === $this->_partial_query)
			return NULL;
		
		return $this->findAll(
			$this->createFindByQuery($field, $value)
		);
	}
	
	/**
	 * Create a new record and return the created record. This accepts a
	 * named record, a PQL query, or a SQL query.
	 */
	public function post($query, array $args = array(), $return = TRUE) {
		
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
			$results[] = $this->_ds->update($stmt, $args);
		
		return count($results) == 1 ? array_pop($results) : $results;
	}
	
	/**
	 * Update a record and return the updated record. This accepts a named
	 * record, a PQL query, or a SQL query.
	 */
	public function put($query, array $args = array()) {
		$query = $this->getQuery($query, QueryCompiler::MODIFY);
		return $this->_ds->update($query);
	}
}