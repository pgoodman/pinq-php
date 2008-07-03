<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// define a constant to select everything from a model an another constant
// to represent a missing value, that is, a value that will be substituted
// later on
!defined('ALL') && define('ALL', INF)
				&& define('_', -INF);


/**
 * from(string $model_name[, $model_alias]) <==> (new Query)->from(...) -> Query
 *
 * Start off a new PQL query in the context for $model_alias. If $model_alias
 * is not set it will default to $model_name.
 *
 * @author Peter Goodman
 */
function from($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * in(...) <==> from(...) -> Query
 *
 * @author Peter Goodman
 */
function in($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * to(...) <==> from(...) -> Query
 *
 * @author Peter Goodman
 */
function to($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * Class that describes queries to hypothetical data structures. Known within
 * the framework as PQL (PINQ Query Language) queries. The public methods of
 * this class are chainable.
 *
 * @example
 *     Selecting from a single model. Each query is equivalent:
 *         from('model_a', 'a')->select(ALL)->where()->a('id')->eq->_;
 *         from('model_a')->select(ALL)->where()->id->eq->_;
 *         from('model_a')->select(ALL)->where()->id->eq(_);
 *     
 *     Selecting from more than one model without linking:
 *         from('model_a', 'a')->select(ALL)->from('model_b', 'b')->
 *         select(ALL)->where()->a('id')->eq->b('id')->and->a('id')->gt(0);
 *     
 *     Selecting from more than one model with linking (equivalent to above):
 *         from('model_a', 'a')->select(ALL)->from('model_b', 'b')->
 *         select(ALL)->link('a', 'b')->where()->a('id')->gt(0);
 *
 * @note Methods {where, limit, order, group} return a new QueryPredicates
 *       instance.
 * @see QueryPredicates
 * @author Peter Goodman
 */
class Query {
	
	// relation options, more might be added to take advantage of SQL join
	// types
	const PIVOT_LEFT = 1,
	      PIVOT_RIGHT = 2;
	
	// query id
	static protected $query_id = 0;
	
	// stuff needed
	protected $_contexts = array(), // an array of the different sources
	          $_context, // a reference to one of the sources in $_contexts
	          $_links = array(), // query relationships to be satisfied
	          $_pivots = array(), // query pivots following the relationships
	          $_predicates, // query-predicates instance or null
	          $_aliases = array(), // maps query aliases to the model names
	          $_id,
	          $_compiled = FALSE;
	
	/** 
	 * Query(void)
	 */
	public function __construct() {
		
		// give this abstract query a unique id. this is used for transient 
		// query caching
		$this->_id = self::$query_id++;
	}
	
	/**
	 */
	public function __destruct() {
		unset(
			$this->_context,
			$this->_contexts,
			$this->_links,
			$this->_pivots,
			$this->_predicates
		);
	}
	
	/**
	 * clone $q -> $q->__clone(void)
	 *
	 * Make sure that in a cloned query the internal query id has been changed.
	 */
	public function __clone() {
		$this->_id = self::$query_id++;
	}
	
	/**
	 * $q->setCompiled(void) -> void
	 *
	 * Tell this instance (and its related QueryPredicates instance if exists)
	 * that it has been compiled. Any future additions/changes to either this
	 * instance or the related QueryPredicates instance invalidates this state.
	 */
	public function setCompiled() {
		$this->_compiled = TRUE;
		if($this->_predicates)
			$this->_predicates->setCompiled();
	}
	
	/**
	 * $q->isCompiled(void) -> bool
	 *
	 * Check if a query and its related query predicates object have been
	 * compiled yet or not. This is used in query caching.
	 */
	public function isCompiled() {
		
		if(NULL !== $this->_predicates)
			return $this->_compiled && $this->_predicates->isCompiled();
		
		return $this->_compiled;
	}
	
	/**
	 * $q->getId() -> int
	 *
	 * Get this query's ID. Each query instance has an internal id that is 
	 * different from every other query instances. When a query is cloned the
	 * cloned query gets a new ID. This ID is used for caching purposes along
	 * with Query::isCompiled().
	 */
	public function getId() {
		return $this->_id;
	}
	
	/**
	 * $q->setPredicates(QueryPredicates) -> void
	 *
	 * Set/change the predicates object that this query is linked to.
	 */
	public function setPredicates(QueryPredicates $predicates) {
		
		if($this->_predicates !== NULL)
			$this->_compiled = FALSE;
		
		$this->_predicates = $predicates;
	}
	
	/**
	 * $q->getPredicates(void) -> QueryPredicates
	 *
	 * Get the predicates object that this instance is associated with. If
	 * this isntance is not associated with a QueryPredicates obeject then
	 * NULL is returned.
	 */
	public function getPredicates() {
		return $this->_predicates;
	}
	
	/**
	 * $q->getRelatios(void) -> &array
	 *
	 * Get the relations defined in the query. This is an associative
	 * multidimensional array. If, for example, a link is specified in a query
	 * in the form of:
	 * ..->link('a', 'b')->link('a', 'c')->link('b', 'c')->...
	 * then this will return an array formatted like:
	 * array(
	 *     'a' => array('b', 'c'),
	 *     'b' => array('c'),
	 * )
	 */
	public function &getRelations() {
		return $this->_links;
	}
	
	/**
	 * $q->getPivots(void) -> array
	 *
	 * Get the pivots defined in the query. Pivots are similar to relations as
	 * pivoting is performed on a relation. In linking two models together in
	 * a query, for example: ..->link('a', 'b')->.., one can specify an optional
	 * third parameter for linking flags. In this third parameter one can, among
	 * other things, specify a pivot direction. If we were to pivot the
	 * relation between models 'a' and 'b' to the right, thus around 'b', then
	 * the relations would be added as usual to the query and a predicate would
	 * be added (at compile time) to the query on the field in 'b' that 
	 * (in)directly maps to 'a'. The field is set as a keyed substitute of
	 * itself.
	 *
	 * @example 
	 *     The following is a PQL query with and without pivoting when
	 *     compiled to SQL:
	 * 
	 *     from('a')->select(ALL)->from('b')->select(ALL)->link('a', 'b');
	 *
	 *         SELECT a.*, b.* FROM a INNER JOIN b ON a.id=b.id
	 *     
	 *     from('a')->select(ALL)->
	 *     from('b')->select(ALL)->link('a', 'b', Query::PIVOT_RIGHT);
	 *
	 *         SELECT a.*, b.* FROM a INNER JOIN b ON a.id=b.id WHERE b.id=:id
	 *
	 * The above example shows that pivoting adds in a condition that restricts
	 * the results returned form a query. Pivots also take advantage of
	 * keyed susbtitute values, meaning data from either 'a' or 'b' (depending
	 * on the pivot direction) needs to be supplied to fulfill the conditions.
	 */
	public function getPivots() {
		return $this->_pivots;
	}
	
	/**
	 * $q->getUnaliasedModelName(string $model_alias) -> string
	 *
	 * Given either the alias to an external model name or the external model
	 * name itself, return the external model name. Model name aliases allows
	 * for the same model to be used multiple times in different ways in a
	 * single query and work as expected.
	 */
	public function getUnaliasedModelName($model_alias) {
		if(isset($this->_aliases[$model_alias]))
			return $this->_aliases[$model_alias];		
		return $model_alias;
	}
	
	/**
	 * $q->getAliases(void) -> &array
	 *
	 * Return an associative array mapping model aliases to external model
	 * names.
	 *
	 * @note The external model names are also keys in the aliases array
	 *       mapping to themselves.
	 */
	public function &getAliases() {
		return $this->_aliases;
	}
	
	/**
	 * $q->getContext(string $model_alias) -> array
	 *
	 * Return a context from the query. A query context, unlike a QueryPredicates
	 * context, is everything defined for a particularly aliased model.
	 *
	 * @example 
	 *     Everything surrounded in { and } belongs to a specific context:
	 *         {from('a')->select(ALL)}->
	 *         {from('b')->select(ALL)}->
	 *         link('a', 'b')->...
	 *
	 * Thus, from the above example, everything specific to a single model
	 * belongs to that model's context within the query.
	 */
	public function getContext($model_alias) {
		if(isset($this->_contexts[$model_alias]))
			return $this->_contexts[$model_alias];
		
		return NULL;
	}
	
	/**
	 * $q->getContexts(void) -> array
	 *
	 * Return all information specific to individual models that are sources
	 * within the query.
	 *
	 * @see Query::getContext(...)
	 */
	public function getContexts() {
		return $this->_contexts;
	}
	
	/**
	 * $q->setContext(string $model_name, string $model_alias) -> void
	 *
	 * Set the current context to be a specific sourece. Contexts are identified
	 * by their model aliases (where model names are the external model names).
	 * If a context does not exist for a particular model alias then one is
	 * created.
	 */
	protected function setContext($model_name, $model_alias) {
		
		$this->_compiled = FALSE;
		
		// create a new context
		if(!isset($this->_contexts[$model_alias])) {
			
			$this->_contexts[$model_alias] = array(
				'alias' => $model_alias,
				'name' => $model_name,
				'select_fields' => array(),
				'select_counts' => array(),
				'modify_values' => array(),
			);
			
			// make sure that we can always find out the model name used for
			// a given alias
			$this->_aliases[$model_alias] = $model_name;
			$this->_aliases[$model_name] = $model_name;
			
			if(!isset($this->_links[$model_alias]))
				$this->_links[$model_alias] = array();
		}
		
		// set the current context
		$this->_context = &$this->_contexts[$model_alias];
	}
	
	/**
	 * $q->from(string $model_name[, string $model_alias]) -> Query
	 *
	 * Set the current context to be the source identified by either
	 * $model_alias (if given) or $model_name.
	 */
	public function from($model_name, $model_alias = NULL) {
		
		if(NULL === $model_alias)
			$model_alias = $model_name;
		
		// set the current context
		$this->setContext($model_name, $model_alias);
		
		return $this;
	}
	
	/**
	 * $q->in(...) <==> $q->from(...)
	 */
	public function in($model_name, $model_alias = NULL) {
		return $this->from($model_name, $model_alias);
	}
	
	/**
	 * $q->select([string $field1[, string $field 2[, ...]]]) -> Query
	 * $q->select([array $fields]) -> Query
	 *
	 * Select some fields from the current context (model). If an array is
	 * passed as the first argument to select then it will be considered to
	 * have all the field names in it. Passing an array in allows the programmer
	 * to define custom aliases for fields (not that they are necessary).
	 */
	public function select() {
		$args = func_get_args();
		
		// we're dealing with some 
		if(count($args) == 1) {
			if(is_array($args[0]))
				$args = $args[0];
		}
		
		if(empty($args))
			return $this;
		
		$this->_compiled = FALSE;
		
		// TODO: in_array very oddly didn't work
		if(ALL === $args[0])
			$args = array((string)ALL => ALL);
		
		// merge in the new select fields
		$this->_context['select_fields'] = array_merge(
			$this->_context['select_fields'],
			$args
		);
		
		return $this;
	}
	
	/**
	 * $q->count(string $field[, string $alias]) -> Query
	 *
	 * Add a COUNT field to the current context. By default the alias of a
	 * count field is count_$field; however, if an alias is passed in then it
	 * will be only the $alias.
	 */
	public function count($field, $alias = NULL) {
		
		$this->_compiled = FALSE;
		$field = (string)$field;
		
		if(NULL === $alias)
			$alias = $field;
		else
			$alias = "count_{$field}";
		
		$this->_context['select_counts'] = array_merge(
			$this->_context['select_counts'],
			array($alias => $field)
		);
		
		return $this;
	}
	
	/**
	 * $q->link(string $left_alias, string $right_alias[, int $flags = 0])
	 * -> Query
	 *
	 * Link two models together by their aliases. An optional $flags integer
	 * can be passed in where various options can be ORed in. Current options
	 * include pivot directions.
	 *
	 * @example
	 *     Link model 'b' on to 'a':
	 *         from('a')->select(ALL)->from('b')->select(ALL)->link('a', 'b')
	 *
	 *     Link model 'c' on to 'b' and 'b' on to 'a':
	 *         from('a')->select(ALL)->
	 *         from('b')->select(ALL)->
	 *         from('c')->select(ALL)->
	 *         link('a', 'b')->
	 *         link('b', 'c')-> ...
	 *
	 * @see Query::getPivots(...)
	 */
	public function link($left_alias, $right_alias, $flags = 0) {
		
		$this->_compiled = FALSE;
		
		// boring type casts
		$flags = (int)$flags;
		$left_alias = (string)$left_alias;
		$right_alais = (string)$right_alias;
		
		// read the error: you can't link a model to itself using the same two
		// aliases.
		/*
		if($left_alias === $right_alias) {
			throw new UnexpectedValueException(
				"PQL Query Error: Cannot link a model to itself using the ".
				"same model aliases. To link a model to itself, make sure ".
				"that the uses of the model in the query have different ".
				"aliases."
			);
		}*/
		
		// common error
		$error = "The model [%s] has does not exist (yet?) in the PQL query ".
		         "and thus cannot be used for linking.";
		
		// make sure that the two linked aliases exist.
		if(!isset($this->_contexts[$left_alias]))
			throw new UnexpectedValueException(sprintf($error, $left_alias));
		
		else if(!isset($this->_contexts[$right_alias]))
			throw new UnexpectedValueException(sprintf($error, $right_alias));
		
		// create the relationships array and add in the link
		if(!isset($this->_links[$left_alias]))
			$this->_links[$left_alias] = array();
		
		if(!in_array($right_alias, $this->_links[$left_alias]))
			$this->_links[$left_alias][] = $right_alias;
		
		// are we doing any pivoting?
		if($pivot = $flags & (self::PIVOT_LEFT | self::PIVOT_RIGHT)) {
			if(!isset($this->_pivots[$left_alias]))
				$this->_pivots[$left_alias] = array();
			
			$this->_pivots[$left_alias][$right_alias] = $pivot;
		}
		
		return $this;
	}
	
	/**
	 * $q->set({string,array} $key[, mixed $value]) -> Query
	 *
	 * Set (key,value) pairs, or a single (key,value) pair to the current
	 * context. This is used mainly for doing INSERT and UPDATE queries.
	 *
	 * @example
	 *     in('model_name')->set('a', 10)->set(array(
	 *         'b' => 20,
	 *         'c' => TRUE,
	 *     ))->...
	 */
	public function set($key, $value = NULL) {
		
		$this->_compiled = FALSE;
		
		$keys = is_array($key) ? $key : array((string)$key => $value);
				
		$this->_context['modify_values'] = array_merge(
			$this->_context['modify_values'],
			$keys
		);
		
		return $this;
	}
	
	/**
	 * $q->where(void) <==> (new QueryPredicates)->where() -> QueryPredicates
	 */
	public function where() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->where();
	}
	
	/**
	 * $q->group(void) <==> (new QueryPredicates)->group() -> QueryPredicates
	 */
	public function group() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->group();
	}
	
	/**
	 * $q->order(void) <==> (new QueryPredicates)->order() -> QueryPredicates
	 */
	public function order() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->order();
	}
	
	/**
	 * $q->limit(int $start[, int $offset]) 
	 * <==> (new QueryPredicates)->limit(...) -> QueryPredicates
	 */
	public function limit($start, $offset = NULL) {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->limit($start, $offset);
	}
}
