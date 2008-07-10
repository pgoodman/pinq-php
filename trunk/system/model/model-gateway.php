<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class that links the model layer and the data sources. It deals with finding
 * and manipulation records in the data source by using the models to fill in 
 * the relationships between them and validate data.
 *
 * @author Peter Goodman
 * @todo Refactoring is needed to separate generic and named model gateway
 *       functionality.
 */
abstract class ModelGateway implements Gateway {
	
	protected $_models,
	          $_relations,
	          $_ds,
	          $_partial_query,
	          $_model_name,
	          $_cached_queries = array(),
	          $_cached_relations = array();
	
	/**
	 * ModelGateway(ModelDictionary, ModelRelations, DataSource[, string $name])
	 *
	 * Bring in all resources needed to relate models to each other, query a
	 * data source, and associate the results of those queries to models.
	 */
	final public function __construct(ModelDictionary $models, 
	                                   ModelRelations $relations,
	                                       DataSource $ds, 
	                                                  $name = NULL) {
		
		$this->_models = $models;
		$this->_ds = $ds;
		$this->_model_name = $name;
		$this->_relations = $relations;
		
		$this->__init__();
	}
	
	/**
	 */
	final public function __destruct() {
		
		$this->__del__();
		
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
	 * $g->setPartialQuery(Query) -> void
	 *
	 * Set a partial query to the model gateway. This is internally used when
	 * using a gateway to a specific model. The partial query is a way to build
	 * up part of a PQL query so that further operations on a specific model
	 * can be streamlined by only specifying a preficates query.
	 *
	 * @internal
	 */
	public function setPartialQuery(Query $query) {
		$this->_partial_query = $query;
	}
	
	/**
	 * $g->getPartialQuery(void) -> Query
	 *
	 * If there is a partial query stored in the gateway then this method will
	 * return a clone of that query. If no such query is stored then a new 
	 * PQL query (Query instance) is returned.
	 *
	 * @internal
	 */
	public function getPartialQuery() {
		if(NULL !== $this->_partial_query)
			return clone $this->_partial_query;
		
		return new Query;
	}
	
	/**
	 * $g->__get(string $model_name) <==> $g->$model_name -> ModelGateway
	 *
	 * @see ModelGateway::__call(...)
	 */
	public function __get($model_name) {
		return $this->__call($model_name, array(ALL));
	}
	
	/**
	 * $g->__call(string $model_name[, array $select]) -> ModelGateway
	 *
	 * Get a new model gateway specific to a model by identifying it with its
	 * external model name. The model gateway returned is pre-populated with a
	 * partial PQL query with the model as a source of data. 
	 */
	public function __call($model_name, array $select = array(ALL)) {
		
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
		
		// set the partial query to the model
		$gateway->setPartialQuery(
			$this->getPartialQuery()->from($model_name)->select($select)
		);
		
		return $gateway;
	}
	
	/**
	 * $q->getCompiledQuery_Record(Record, int $type) -> string
	 *
	 * Get the compiled query given a Record object.
	 */
	private function getCompiledQuery_Record(Record $record, 
	                                                $type, 
	                                         array &$args = array()) {
	
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
		while($record instanceof OuterRecord)
			$record = $record->getRecord();
		
		// get the model name that this record belongs to
		$model_name = $record->getModelName();
		
		// we can't work with an ambiguous record
		if(NULL === $model_name) {
			throw new InvalidArgumentException(
				"Cannot build relationship off of ambiguous record. If ".
				"this record has sub-records (ie: the PQL query selected ".
				"from multiple models at once) then the relationship ".
				"needs to be called on one of the sub records. If this ".
				"record was not found through PQL then you cannot use ".
				"this feature."
			);
		}
		
		// if we haven't already created a query for this relation, then
		// do so. the reason why these types of relations are cached is
		// because if they are called on in a loop then this function
		// would otherwise be very slow
		if(!isset($this->_cached_relations[$model_name][$type])) {
											
			// clone it so that we can use it again if necessary
			$query = $this->getPartialQuery();
			if($query instanceof QueryPredicates)
				$query = $query->getQuery();
			
			// add in the predicates to make linking and pivoting to the
			// record possible
			$query->from($model_name)->link(
				$this->_model_name, 
				$model_name, 
				Query::PIVOT_RIGHT
			);
			
			// compile the query and cache it
			$query = $this->getCompiledQuery_Query($query, $type, $args);
			$this->_cached_relations[$model_name][$type] = $query;
		}
		
		return $this->_cached_relations[$model_name][$type];
	}
	
	/**
	 * $q->getCompiledQuery_QueryPredicates(QueryPredicates, int $type) 
	 * -> string
	 *
	 * Get the compiled query given a QueryPredicates object.
	 */
	private function getCompiledQuery_QueryPredicates(
	                                  QueryPredicates $qp, 
	                                                  $type,
	                                           array &$args = array()) {
	
		if(NULL === $qp->getQuery()) {
			
			// set the partial query to this query predicates object
			$query = $this->getPartialQuery();
			if($query instanceof QueryPredicates)
				$query = $query->getQuery();
			
			$predicates = $query->getPredicates();

			// the partial query has no predicates, this is easy
			if(NULL == $predicates)
				$query->setPredicates($qp);
			
			// the partial query has predicates, merge $query into the
			// predicates of the partial query
			else
				$predicates->merge($qp);
			
		} else
			$query = $qp->getQuery();
				
		return $this->getCompiledQuery_Query($query, $type, $args);
	}
	
	/**
	 * $q->getCompiledQuery_Query(Query, int $type) -> string
	 *
	 * Get the compiled query given a Query object.
	 */
	private function getCompiledQuery_Query(Query $query, 
	                                              $type, 
	                                       array &$args = array()) {
	
		$query_id = $query->getId();
		$is_set_type = $type & (QueryCompiler::INSERT | QueryCompiler::UPDATE);
		
		// the query has already been compiled and cached, use it.
		if(!$is_set_type && $query->isCompiled()) {
			if(isset($this->_cached_queries[$type][$query_id]))
				return $this->_cached_queries[$type][$query_id];
		}
		
		// nope, we need to compile the query. $args is passed into the query
		// compiler for use in insert/update queries where the validity of the
		// data being used needs to be guaranteed.
		$stmt = $this->compileQuery(
			$query,
			$type,
			$args
		);
		
		// tell the query and its predicates that it has been compiled
		// it is done after the query has been compiled because the
		// query compiler might add in predicates.
		$query->setCompiled();
		
		return $this->_cached_queries[$query_id] = $stmt;
	}
	
	/**
	 * $g->getCompiledQuery(mixed $query, enum int $type) -> string
	 *
	 * Get a string representation of a query. This accepts instances of:
	 * Query, QueryPredicates, and Record, as well as string queries. This
	 * mthod behaves in the following ways given the type of $query:
	 *
	 * string
	 *     $query is returned as-is.
	 * Record
	 *     To pass a record as a query (known as pivoting on a record),
	 *     the gateway must have a partial query. If there is none then
	 *     an InvalidArgumentException is thrown. The record must also
	 *     not be ambiguous (it must be directly associated with a model
	 *     definition). If the record is not associated with a model name
	 *     then a InvalidArgumentException is thrown. Assuming everything
	 *     is right with the Record, it is used to extend the partial
	 *     query in model gateway.
	 * QueryPredicates
	 *     If the predicates object is not associated to a Query object
	 *     and there is partial query in the model then the predicates
	 *     object is attached to that query and we then work with the
	 *     method as if a Query object was passed. If a query is associated
	 *     with the predicated object then we use it.
	 * Query
	 *     The query is compiled using the query compiler associated with
	 *     this model gateway/data source and returned.
	 *
	 * The type of the query is an integer belonging to:
	 *     enum {
	 *         QueryCompiler::SELECT, 
	 *         QueryCompiler::UPDATE, 
	 *         QueryCompiler::INSERT, 
	 *         QueryCompiler::DELETE,
	 *     }
	 *
	 * @internal
	 */
	protected function getCompiledQuery($query, $type, array &$args = array()) {
		
		if(is_string($query))
			return $query;
		
		if($query instanceof Record)
			$func = 'getCompiledQuery_Record';
		
		else if($query instanceof QueryPredicates)
			$func = 'getCompiledQuery_QueryPredicates';
		
		else if($query instanceof Query)
			$func = 'getCompiledQuery_Query';
		
		else {
			throw new DomainException(
				"Unexpected query type."
			);
		}
		
		return $this->$func($query, $type, $args);
	}
	
	/**
	 * $g->createRecord([array $data[, string $model_name]]) -> Record
	 *
	 * Return a new Record object with the default unsaved data in it.
	 */
	public function createRecord(array $data = array(), $model_name = NULL) {
		if(NULL === $model_name)
			$model_name = $this->_model_name;
		
		if(NULL === $model_name)
			$record = new InnerRecord;
		else {
			$definition = $this->_models[$model_name];
			$class = $definition->getRecordClass();
			$record = new $class($data);
			$record->setModelName($model_name);
		}
		
		return $record;
	}
	
	/**
	 * $g->compileQuery(Query, enum int $type, array &$args) -> string[]
	 *
	 * Get the query compiler for this gateway / data source and compile the
	 * query into a string (or array of strings).
	 */
	abstract protected function compileQuery(Query $query, $type, array &$args);
	
	/**
	 * $g->getRecord(resource) -> Record
	 *
	 * Return an model-specific (or generic) Record object.
	 */
	abstract protected function getRecord($result_resource);
	
	/**
	 * $g->getRecordIterator(resource) -> RecordIterator
	 *
	 * Return a model-specific RecordIterator object.
	 */
	abstract protected function getRecordIterator($result_resource);
	
	/**
	 * $g->selectResult(string $query[, array $args]) -> resource
	 *
	 * Query the datasource and return a result resource.
	 *
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	protected function selectResult($query, array &$args = array()) {
		
		if($query instanceof Record)
			$args = $query->toArray();
		
		// get the query, and compile it if necessary
		$query = $this->getCompiledQuery($query, QueryCompiler::SELECT, $args);
		
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
	 * $g->get(mixed $query[, array $args]) -> Record
	 *
	 * Get a single record from the data source. This method accepts all forms
	 * of query values as ModelGateway::getCompiledQuery(). If no record can be found
	 * then NULL is returned.
	 *
	 * The $args array is an array of data to substitute into the query. This
	 * array can be either associative or numeric, but not both (unless keys
	 * are indexed numerically in the queyr, eg: :1).
	 *
	 * @example
	 *     PQL query:
	 *         $record = $g->get(from('model_name')->select(ALL));
	 *     
	 *     PQL query with substitute value:
	 *         $record = $g->get(
	 *             from('model_name')->select(ALL)->where->id->eq-_,
	 *             array(10)
	 *         );
	 *     
	 *     SQL query with substitute value:
	 *         $record = $g->get(
	 *             "SELECT * FROM model_name WHERE id=?",
	 *             array(10)
	 *         );
	 *     
	 *     PQL query with keyed-substitute value:
	 *         $record = $g->get(
	 *             from('model_name')->select(ALL)->where->id->eq-_('id'),
	 *             array('id' => 10)
	 *         );
	 *     
	 *     SQL query with keyed-substitute value:
	 *         $record = $g->get(
	 *             "SELECT * FROM model_name WHERE id=:id",
	 *             array('id' => 10)
	 *         );
	 *     
	 *     PQL predicates query + partial query:
	 *         $record = $g->model_name->get(where()->id->eq->_);
	 *     
	 *     using a record to pivot a relation:
	 *         $record = $g->model_name->get($related_record);
	 *     
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	public function get($query, array $args = array()) {
		
		// add in a limit to the query to speed up the query given that we
		// are actually going through findAll
		if($query instanceof QueryPredicates || $query instanceof Query)
			$query->limit(1);
		
		// find all results
		$result = $this->selectResult($query, $args);
		
		if(!$result)
			return NULL;
		
		return $this->getRecord($result);
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
	 *
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	public function getValue($query, array $args = array()) {
		$row = $this->get($query, $args);
		
		// oh well, no row to return
		if(NULL === $row)
			return NULL;

		// dig deep into the record if we are dealing with an outer record
		while($row instanceof OuterRecord)
			$row = $row->getRecord();
		
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
	 * $g->getAll(mixed $query[, array $args]) -> {RecordIterator, void}
	 *
	 * Get many records from the data source and return them in a RecordIterator
	 * object. If the query fails this method returns NULL.
	 *
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	public function getAll($query, array $args = array()) {
		$result = $this->selectResult($query, $args);
		
		if(!$result)
			return NULL;
		
		return $this->getRecordIterator($result);
	}
	
	/**
	 * $g->delete(mixed $query[, array $args]) -> bool
	 *
	 * Delete records from the data source. If a Record is passed in and it is
	 * not named an UnexpectedValueException will be thrown.
	 *
	 * @note Deleting a record using a Record object is currently note supported.
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	public function delete($what, array $args = array()) {
			
		// deleting based on a record. this is a bit sketchy as we're going to
		// need to pivot on something. usually, for example with a database,
		// the primary key would be used, but we don't know what primary keys
		// are, but we can make a decent guess of it by using all integer
		// fields
		if($what instanceof Record) {
			
			// the record is not named, ie: we cannot identify which model
			// is having row(s) deleted from it.
			if(NULL === $what->getModelName()) {
				throw new UnexpectedValueException(
					"RecordGateway::delete() expected first argument to be ".
					"an unambiguous record."
				);
			}
			
			// have the record delete itself
			die('TODO');
		
		// force it to be a string, oh well :P
		} else {
			$query = $this->getCompiledQuery(
				$what, 
				QueryCompiler::DELETE, 
				$args
			);
		}
		
		return $this->_ds->update($query, $args);
	}
	
	/**
	 * $g->getByPredicates(string field, mixed $value) -> QueryPredicates
	 *
	 * Create the predicates needed to find one or more rows from the data 
	 * source given a field a what value it should have. The value passed in
	 * to $value must be scalar and cannot be a substitute value (_).
	 *
	 * @internal
	 */
	protected function getByPredicates($field, $value) {
		
		$field = (string)$field;
		
		// make sure a substitute value isn't being passed in
		if(_ === $value) {
			throw new InvalidArgumentException(
				"ModelGateway::find[All]By() does not accept a substitute ".
				"value for the value of a field."
			);
		}
		
		$definition = $this->_models[$this->_model_name];
		
		// make sure the field actually exists
		if(!$definition->hasField($field)) {
			throw new InvalidArgumentException(
				"ModelGateway::get[All]By() expects first argument to be an ".
				"existing field in model definition [{$this->_model_name}]."
			);
		}
		
		// create the PQL query and coerce the value that's going into the
		// query
		return where()->{$this->_model_name}($field)->eq(
			$definition->coerceValueForField($field, $value)
		);
	}
	
	/**
	 * $m->getBy(string $field, mixed $value) -> Record
	 * 
	 * Get a single record from the data source where the record's field=value.
	 *
	 * @see ModelGateway::get(...)
	 */
	public function getBy($field, $value) {
		if(NULL === $this->_partial_query)
			return NULL;
		
		return $this->get(
			$this->getByPredicates($field, $value)
		);
	}
	
	/**
	 * $g->getAllBy(string $field, mixed $value) -> RecordIterator
	 *
	 * Get many records from the data source where each record's field=value.
	 *
	 * @see ModelGateway::getAll(...)
	 */
	public function getAllBy($field, $value) {
		if(NULL === $this->_partial_query)
			return NULL;
		
		return $this->getAll(
			$this->getByPredicates($field, $value)
		);
	}
	
	/**
	 * $g->insert(mixed $query[, array $args]) -> bool
	 *
	 * Create a new record and return the created record. This accepts a
	 * named record, a PQL query, or a SQL query.
	 *
	 * @note This will only compile one query for the first model used in the
	 *       PQL query and ignore the other models and the predicates.
	 * @see ModelGateway::getCompiledQuery(...)
	 * @todo Make sure that the query hasn't already been compiled as another
	 *       type of query else there will be problems.
	 */
	public function insert($query, array $args = array()) {
		
		// dealing with a record, turn it into a query
		if($query instanceof Record) {
			$record = $query;
			
			if(NULL === ($model_name = $record->getModelName())) {
				if(NULL === ($model_name = $this->_model_name)) {
					throw new InvalidArgumentException(
						"ModelGateway::insert() expects either named record ".
						"or for the gateway to be named when a Record object ".
						"is passed. Neither condition satisfied."
					);
				}
			}
			
			// build a query
			$query = from($model_name)->set($record->toArray());
		}
		
		// compile the query
		$query = $this->getCompiledQuery(
			$query, 
			QueryCompiler::INSERT, 
			$args
		);
		
		return (bool)$this->_ds->update($query, $args);
	}
	
	/**
	 * $g->update(mixed $query[, array $args]) -> bool
	 *
	 * Modify any number of records in the data source and return if the update
	 * was successful.
	 *
	 * @see ModelGateway::getCompiledQuery(...)
	 */
	public function update($query, array $args = array()) {
		$query = $this->getCompiledQuery(
			$query, 
			QueryCompiler::UPDATE, 
			$args
		);
		
		return (bool)$this->_ds->update($query, $args);
	}
	
	/**
	 * $g->__init__(void) -> void
	 *
	 * Hook called after class construction.
	 */
	protected function __init__() { }
	
	/**
	 * $g->__del__(void) -> void
	 *
	 * Hook called before class resources are released.
	 */
	protected function __del__() { }
}
