<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// define a constant to select everything from a model an another constant
// to represent a missing value, that is, a value that will be substituted
// later on
!defined('ALL') && define('ALL', INF)
				&& define('_', -INF);

/**
 * The PINQ starter functions don't exist yet.
 */
if(!function_exists("from")) {
	
	// return a new pinq object
	function &from($ds, $alias = NULL) {
		$pinq = new QueryLanguage;
		$pinq->setDataSource($ds, $alias);
		return $pinq;
	}
	
	// alias to from, makes more semantic sense for other functions.
	function &to($ds, $alias = NULL) {
		$from = &from($ds, $alias);
		return $from;
	}
	
	// alias to from, makes more semantic sense for other functions.
	function &in($ds, $alias = NULL) {
		$from = &from($ds, $alias);
		return $from;
	}
}

/**
 * A language to query a hypothetical data structure. This is a stack because
 * as we are adding and selecting data from sources we need to know which
 * source to add the select fields to. It doesn't need to be a full-on stack
 * but it was simple :P 
 * @author Peter Goodman
 */
abstract class Query extends Stack {
	
	// relation options
	const PIVOT_LEFT = 1,
	      PIVOT_RIGHT = 2;
	
	static protected $query_id = 0;
	
	public $_aliases = array(), // maps aliases to source names
		   $_sources = array(), // the data sources being used
		   $_fields = array(), // what do we want to find from each 
									// data source?
		   $_counts = array(), // things in the datasources to count
		   $_values = array(), // values for a create / delete / update
		   $_relations = array(),
		   $_pivots = array(),
		   $_predicates = NULL, // conditions to be met on the data 
		   $_id;						// returned
	
	/** 
	 * Constructor, very little to set up.
	 */
	public function __construct() {
		
		// give this abstract query a unique id. this is used for query
		// caching purposes
		$this->_id = self::$query_id++;		
	}
	
	/**
	 * Destructor, clear up everything.
	 */
	public function __destruct() {
		unset(
			$this->_sources,
			$this->_fields, 
			$this->_counts, 
			$this->_values, 
			$this->_predicates,
			$this->_relations,
			$this->_pivots
		);
	}
	
	/**
	 * Clone the query. We need to make sure to change the query id.
	 */
	public function __clone() {
		$this->_id = self::$query_id++;
	}
	
	/**
	 * Even if we don't supply an alias, we want a one-to-one relationship
	 * between the keys in all of the various arrays.
	 * @internal
	 */
	protected function addDataSource($ds, $alias) {
		
		// map the alias back to the source name for later use (in the
		// concrete query)		
		$this->_aliases[$alias] = $ds;
		$this->_aliases[$ds] = $ds;
		
		// are we dealing with heriarchical data?
		if(FALSE !== strpos($ds, '.')) {
			$ds = explode('.', $ds);
		}

		// references can only be made through aliasing. why? well, if we are
		// linking a source to itself, the only way to reliably do this is to
		// use aliasing.
		$this->_sources[$alias] = $ds;
		
		// set up the default values for this alias
		$this->_fields[$alias] = array();
		$this->_counts[$alias] = array();
		$this->_values[$alias] = array();
		$this->_relations[$alias] = array();
		$this->push($alias);
	}
	
	/**
	 * If the supplied datasource isn't available, create it. If it is, switch
	 * to it.
	 */
	public function setDataSource($ds, $alias = NULL) {
		
		// pop whatever is on the stack off
		$this->silentPop();
		
		// no alias, default to casting the datasource to a string
		if(NULL === $alias)
			$alias = (string)$ds;
		
		// this data source doesn't yet exist in the query, add it
		if(!isset($this->_sources[$alias]))
			$this->addDataSource($ds, $alias);
		
		// this data source exists, push its alias the stack
		else
			$this->push($alias);
	}
	
	/**
	 * Add to one of the arrays {count, find} and store things in the items
	 * array as well.
	 * @internal
	 */
	protected function addTo(array &$array, $ds_alias, array $items) {
		foreach($items as $alias => $item) {
			
			// if an integer-keyed array was passed in then set the default
			// mapping to be (item name -> item name)
			if(!is_string($alias))
				$alias = (string)$item;
			
			// add the count/find array
			$array[$ds_alias][$alias] = $item;
		}
	}
	
	/**
	 * Specify that we're getting something from the current datasource
	 * on the stack. We can only find something after we've told it what
	 * data source to look in.
	 */
	public function addItemsToFind(array $items = array()) {
		
		$alias = $this->top();
		
		// so that we can pass an associative array of mappings in
		if(count($items) === 1 && is_array($items[0]))
			$items = $items[0];
		
		$this->addTo($this->_fields, $alias, $items);
	}
	
	/**
	 * Tell the datasource that we'll be counting something. This is sort of
	 * a special case as it affects the types of queries that will be built
	 * from this class.
	 */
	public function addCount(array $items = array()) {
		$alias = $this->top();
		$this->addTo($this->_counts, $alias, $items);
	}

	/**
	 * Add to the values array.
	 */
	public function addValues(array $values = array()) {
		$alias = $this->top();
		
		// no values, full stop
		if(empty($values))
			return;
		
		// 2 values, assume we are supplying a single (key,value) pair
		else if(count($values) == 2) {
			$values = array(
				(string)$values[0] => $values[1],
			);
		
		// an array of key=>value mappings
		} else
			$values = array_shift($values);
		
		$this->_values[$alias] = array_merge($this->_values[$alias], $values);
	}

	/**
	 * State that a relationship should exist between two tables without
	 * explicitly defining how the datasources are related. The order of the
	 * relationships is significant.
	 */
	public function addRelationship($a1, $a2, $options = FALSE) {
		
		$a1 = (string)$a1;
		$a2 = (string)$a2;
		
		if(isset($this->_sources[$a1]) && isset($this->_sources[$a2])) {
			
			if(!isset($this->_relations[$a1]))
				$this->_relations[$a1] = array();
			
			$this->_relations[$a1][] = $a2;
			
			$pivot = $options & (self::PIVOT_LEFT | self::PIVOT_RIGHT);
			
			if($pivot) {
				if(!isset($this->_pivots[$a1]))
					$this->_pivots[$a1] = array();
				
				$this->_pivots[$a1][$a2] = $pivot;
			}
			
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Get the array of predicates.
	 */
	public function getPredicates() {
		return $this->predicates;
	}
	
	/**
	 * Set the predicates array. This is usually called by QueryPredicates
	 * right before the query is compiled.
	 * @internal
	 */
	public function setPredicates(QueryPredicates $predicates) {
		$this->_predicates = $predicates;
	}
}

/**
 * This class just builds up query information; however, it's not up to this 
 * class to know what type of query it is. In fact, the class is simply
 * data. Given this, there are no defined 'update' or 'delete' methods.
 * @author Peter Goodman
 */
class QueryLanguage extends Query {
	
	/**
	 * Constructor, set up the operator table if it hasn't been yet.
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * If we link into predicates using 
	 */
	public function __get($op) {
				
		// return a new predicates object if this is a valid predicate
		// operator
		if(in_array(strtolower($op), array('where',	'group', 'order'))) {
			$this->_predicates = new QueryPredicates($this);
			return $this->_predicates->$op();
		}

		return $this;
	}
	
	public function where() { return $this->__get('where'); }
	public function group() { return $this->__get('group'); }
	public function order() { return $this->__get('order'); }
	
	/**
	 * Predicates limit function.
	 */
	public function limit($start, $offset = NULL) {
		$this->_predicates = new QueryPredicates($this);
		return $this->_predicates->limit($start, $offset);
	}
	
	/**
	 * Parse function calls, this pretty much defines the API to PINQ.
	 */
	public function __call($fn, array $args = array()) {
		
		// ignore this call completely if there are no arguments passed in
		if(!isset($args[0]))
			return $this;
		
		$fn_lower = strtolower($fn);
		
		// handle the function
		switch($fn_lower) {
			
			case 'from':
			case 'in':
				$val = isset($args[1]) ? $args[1] : NULL;
				$this->setDataSource($args[0], $val);
				break;
			
			// find things
			case 'select':
				$this->addItemsToFind($args);
				break;
			
			// set key-value pairs
			case 'set':
				$this->addValues($args);
				break;
			
			// count some items
			case 'count':
				$this->addCount($args);
				break;

			// where clause
			case 'where';
				return $this->getQueryPredicates();
		}
		
		return $this;
	}
	
	/**
	 * Define a relationship between two or more datasources. it's up to
	 * whatever is taking in the abstract query to decide how to actually
	 * establish that relationship.
	 */
	function link($left_alias, $right_alias, $options = 0) {
				
		if(!$this->addRelationship($left_alias, $right_alias, $options)) {
			throw new UnexpectedValueException(
				"Could not relate data models [{$left_aliase}] and ".
				"[{$right_alias}]."
			);
		}
		
		return $this;
	}
}
