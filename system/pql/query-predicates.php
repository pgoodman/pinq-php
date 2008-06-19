<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists('where')) {
	
	function &pql_create_predicates($pred_type, Query $query = NULL, $class = 'AbstractSingleSourcePredicates') {
		$predicates = new $class($query);
		$predicates->addOperator($pred_type);
		return $predicates;
	}
	
	function &where() {
		return pql_create_predicates(QueryPredicates::WHERE);
	}
	
	function &limit() {
		$args = func_get_args();
		$predicates = pql_create_predicates(QueryPredicates::LIMIT);
		$predicates->addOperand(QueryPredicates::VALUE_CONSTANT, $args);
		return $predicates;
	}
	
	function &order() {
		return pql_create_predicates(QueryPredicates::ORDER_BY);
	}
	
	function &group() {
		return pql_create_predicates(QueryPredicates::GROUP_BY);
	}
}

class QueryPredicates {
	
	// an array of sets of predicates
	protected $_predicates,
	          $_query;
	
	/**
	 * Constructor, set up the default set of predicates.
	 */
	public function __construct(Query $query) {
		
		// the predicates table
		$this->_predicates = array(
			'conditions' => array(),
			'order' => array(),
			'group' => array(),
			'limit' => array(),
		);
		
		$this->_query = $query;
	}
	
	public function __call($fn, array $args = array()) {
		
	}
}
