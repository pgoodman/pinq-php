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
		$pinq = new AbstractQueryLanguage;
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
abstract class AbstractQuery extends Stack {
		
	public $aliases		 = array(), // maps aliases to source names
		   $sources		 = array(), // the data sources being used
		   $items		 = array(), // what do we want to find from each 
									// data source?
		   $item_aliases = array(), // aliases => items
		   $counts		 = array(), // things in the datasources to count
		   $values		 = array(), // values for a create / delete / update
		   $relations	 = array(),
		   $predicates   = array(); // conditions to be met on the data 
									// returned
	
	/** 
	 * Constructor, very little to set up.
	 */
	public function __construct() {
		//$this->stack = new Stack;
	}
	
	/**
	 * Destructor, clear up everything.
	 */
	public function __destruct() {
		unset(
			$this->operands, 
			$this->operators, 
			$this->sources,
			$this->items, 
			$this->item_aliases, 
			$this->counts, 
			$this->values, 
			$this->predicates,
			$this->relations
		);
	}
	
	/**
	 * Even if we don't supply an alias, we want a one-to-one relationship
	 * between the keys in all of the various arrays.
	 * @internal
	 */
	protected function addDataSource($ds, $alias) {
		
		// map the alias back to the source name for later use (in the
		// concrete query)
		$this->aliases[$alias] = $ds;
		$this->aliases[$ds] = $ds;

		// are we dealing with heriarchical data?
		if(FALSE !== strpos($ds, '.')) {
			$ds = explode('.', $ds);
		}

		// references can only be made through aliasing. why? well, if we are
		// linking a source to itself, the only way to reliably do this is to
		// use aliasing.
		$this->sources[$alias] = $ds;
		
		// set up the default values for this alias
		$this->items[$alias] = array();
		$this->counts[$alias] = array();
		$this->values[$alias] = array();
		$this->relations[$alias] = array();
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
		if(!isset($this->sources[$alias]))
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
			
			if(!is_string($alias))
				$alias = (string)$item;
			
			// a global lookup table for later
			$this->item_aliases[$alias] = $item;
			
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
		$this->addTo($this->items, $alias, $items);
	}
	
	/**
	 * Tell the datasource that we'll be counting something. This is sort of
	 * a special case as it affects the types of queries that will be built
	 * from this class.
	 */
	public function addCount(array $items = array()) {
		$alias = $this->top();
		$this->addTo($this->counts, $alias, $items);
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
		
		$this->values[$alias] = array_merge($this->values[$alias], $values);
	}

	/**
	 * State that a relationship should exist between two tables without
	 * explicitly defining how the datasources are related. The order of the
	 * relationships is significant.
	 */
	public function addRelationship($a1, $a2) {
		
		$a1 = (string)$a1;
		$a2 = (string)$a2;
		
		if(isset($this->sources[$a1]) && isset($this->sources[$a2])) {
			
			if(!isset($this->relations[$a1]))
				$this->relations[$a1] = array();
			
			$this->relations[$a1][] = $a2;
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
	 * Set the predicates array. This is usually called by AbstractPredicates
	 * right before the query is compiled.
	 * @internal
	 */
	public function setPredicates(array &$predicates) {
		$this->predicates = &$predicates;
	}
}

/**
 * This class just builds up query information; however, it's not up to this 
 * class to know what type of query it is. In fact, the class is simply
 * data. Given this, there are no defined 'update' or 'delete' methods.
 * @author Peter Goodman
 */
class AbstractQueryLanguage extends AbstractQuery {
	
	/**
	 * Constructor, set up the operator table if it hasn't been yet.
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Parse variable requests, these are usually operators.
	 */
	public function __get($op) {
		//$this->parseOperator($op);
		
		if(strtolower($op) === 'where') {
			
			// if we're only working with one source then it makes more sense
			// to work with less clunk operators
			$class = 'AbstractPredicates';
			if(count($this->sources) === 1) {
				
				// should we add in an implicit select(ALL) to the source?
				// this is to make short queries more accessible.
				$alias = $this->top();
				if(empty($this->items[$alias]))
					$this->select(ALL);
				
				$class = 'AbstractSingleSourcePredicates';
			}
			
			// reference into the predicates array and return
			$predicates = new $class($this);
			$predicates->addOperator('where');
			return $predicates;
		}

		return $this;
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
			
			// define a relationship between two or more datasources. it's up
			// to whatever is taking in the abstract query to decide how to
			// actually establish that relationship
			case 'link':
				
				if(!isset($args[1]) || !$this->addRelationship($args[0], $args[1]))
					throw new UnexpectedValueException(
						"Could not relate data sources [{$args[0]}] and ".
						"[{$args[1]}]."
					);

				break;
			
			// count some items
			case 'count':
				$this->addCount($args);
				break;

			// split by underscores and build up the operator
			default:
				
				

				break;
		}
		
		return $this;
	}
}
