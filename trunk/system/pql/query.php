<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// define a constant to select everything from a model an another constant
// to represent a missing value, that is, a value that will be substituted
// later on
!defined('ALL') && define('ALL', INF)
				&& define('_', -INF);


/**
 * Return a new pinq object
 * @author Peter Goodman
 */
function from($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * Return a new pinq object
 * @author Peter Goodman
 */
function in($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * Return a new pinq object
 * @author Peter Goodman
 */
function to($model_name, $model_alias = NULL) {
	$query = new Query;
	return $query->from($model_name, $model_alias);
}

/**
 * A PQL query to query a hypothetical data structure.
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
	 * Constructor, very little to set up.
	 */
	public function __construct() {
		
		// give this abstract query a unique id. this is used for transient 
		// query caching
		$this->_id = self::$query_id++;
	}
	
	/**
	 * Destructor, clear up everything.
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
	 * Clone the query. We need to make sure to change the query id.
	 */
	public function __clone() {
		$this->_id = self::$query_id++;
	}
	
	/**
	 * Tell the query that it has been compiled.
	 */
	public function setCompiled() {
		$this->_compiled = TRUE;
		if($this->_predicates)
			$this->_predicates->setCompiled();
	}
	
	/**
	 * Has this query and its predicates been compiled yet?
	 */
	public function isCompiled() {
		
		if(NULL !== $this->_predicates)
			return $this->_compiled && $this->_predicates->isCompiled();
		
		return $this->_compiled;
	}
	
	/**
	 * Get this query's ID.
	 */
	public function getId() {
		return $this->_id;
	}
	
	/**
	 * Set this query's predicates.
	 */
	public function setPredicates(QueryPredicates $predicates) {
		$this->_predicates = $predicates;
	}
	
	/**
	 * Get the predicates.
	 */
	public function &getPredicates() {
		return $this->_predicates;
	}
	
	/**
	 * Get the relations defined in the query.
	 */
	public function &getRelations() {
		return $this->_links;
	}
	
	/**
	 * Get the pivots defined in the query.
	 */
	public function getPivots() {
		return $this->_pivots;
	}
	
	/**
	 * Get the model name given an alias.
	 */
	public function getUnaliasedModelName($model_alias) {
		if(isset($this->_aliases[$model_alias]))
			return $this->_aliases[$model_alias];		
		return $model_alias;
	}
	
	/**
	 * Get the aliases.
	 */
	public function &getAliases() {
		return $this->_aliases;
	}
	
	/**
	 * Return a contet from the query.
	 */
	public function getContext($model_alias) {
		if(isset($this->_contexts[$model_alias]))
			return $this->_contexts[$model_alias];
		
		return NULL;
	}
	
	/**
	 * Return all contexts.
	 */
	public function getContexts() {
		return $this->_contexts;
	}
	
	/**
	 * Set the current context. If the context does not exist then create it.
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
		}
		
		// set the current context
		$this->_context = &$this->_contexts[$model_alias];
	}
	
	/**
	 * Set the current context.
	 */
	public function from($model_name, $model_alias = NULL) {
		
		if(NULL === $model_alias)
			$model_alias = $model_name;
		
		// set the current context
		$this->setContext($model_name, $model_alias);
		
		return $this;
	}
	
	/**
	 * Alias of from().
	 */
	public function in($model_name, $model_alias = NULL) {
		return $this->from($model_name, $model_alias);
	}
	
	/**
	 * Select some fields from the current context.
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
	 * Create some select count fields.
	 */
	public function count($field, $alias = NULL) {
		
		$this->_compiled = FALSE;
		
		if(NULL === $alias)
			$alias = $field;
		
		$this->_context['select_counts'] = array_merge(
			$this->_context['select_counts'],
			array($alias => $field)
		);
		
		return $this;
	}
	
	/**
	 * Link two models together by their aliases.
	 */
	public function link($left_alias, $right_alias, $flags = 0) {
		
		$this->_compiled = FALSE;
		
		// read the error: you can't link a model to itself using the same two
		// aliases.
		if($left_alias === $right_alias) {
			throw new UnexpectedValueException(
				"PQL Query Error: Cannot link a model to itself using the ".
				"same model aliases. To link a model to itself, make sure ".
				"that the uses of the model in the query have different ".
				"aliases."
			);
		}
		
		// common error
		$error = "The model [%s] has does not exist (yet?) in the PQL query ".
		         "and thus cannot be used for linking.";
		
		// make sure that the two linked aliases exist.
		if(!isset($this->_contexts[$left_alias]))
			throw new UnexpectedValueException(sptrintf($error, $left_alias));
		
		else if(!isset($this->_contexts[$right_alias]))
			throw new UnexpectedValueException(sptrintf($error, $right_alias));
		
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
	 * Set (key,value) pairs, or a single (key,value) pair.
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
	 * Predicate functions.
	 */
	public function where() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->where();
	}
	public function group() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->group();
	}
	public function order() {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->order();
	}
	public function limit($start, $offset = NULL) {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->limit($start, $offset);
	}
}