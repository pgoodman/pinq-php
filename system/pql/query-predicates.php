<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists('where')) {
	
	function &where() {
		$predicates = new QueryPredicates;
		return $predicates->where();
	}
	
	function &limit($start, $offset = NULL) {
		$predicates = new QueryPredicates;
		return $predicates->limit($start, $offset);
	}
	
	function &order() {
		$predicates = new QueryPredicates;
		return $predicates->order();
	}
	
	function &group() {
		$predicates = new QueryPredicates;
		return $predicates->group();
	}
}

/**
 * Build up predicates for a PQL query.
 * @author Peter Goodman
 */
class QueryPredicates extends Stack {
	
	// an array of sets of predicates
	protected $_predicates, // the predicates are stored in reverse-polish
	          $_operators,
	          $_operands,
	          $_query,
	          $_context;
		
	const OPERAND = 1,
	      OPERATOR = 2,
	      SUBSTITUTE = 4;
	
	/**
	 * Constructor, set up the default set of predicates.
	 */
	public function __construct(Query $query = NULL) {
		
		// the predicates table
		$this->_predicates = array(
			'where' => array(),
			'order' => array(),
			'group' => array(),
			'limit' => array(),
			'search' => array(), // a pseudo context
		);
		
		// operators and their precedence levels. high precedence level means
		// that the operator is more difficult to tear apart
		$this->_operators = array(
			'mul' => 4, 'div' => 4,
			'add' => 3, 'sub' => 3,
			'eq' => 2, 'leq' => 2, 'geq' => 2, 'lt' => 2, 'gt' => 2, 'neq' => 2,
			'and' => 1, 'or' => 1, 'xor' => 1,
			
			// prefix operators
			'not' => 5, 'like' => 5, 'search' => 5, 'for' => 5,
		);
		
		$this->_query = $query;
	}
	
	/**
	 * Set/change the current query these predicates work for.
	 */
	public function setQuery(Query $query) {
		$this->query = $query;
	}
	
	/**
	 * Get the query that these predicates link to.
	 */
	public function getQuery() {
		return $this->query;
	}
	
	/**
	 * Set what part of the query we are modifying.
	 * @internal
	 */
	public function setContext($context) {
		$this->_context = &$this->_predicates[$context];
		return $this;
	}
	
	/**
	 * Add an operand.
	 */
	protected function addOperand($key, $value) {
		$this->_context[] = array(self::OPERAND, $key, $value);
		return $this;
	}
	
	/**
	 * Add an operator to the predicates list. Operators are only really for
	 * the conditions list.
	 */
	protected function addOperator($key) {
		$this->_context = $array(self::OPERATOR, $key, NULL);
		return $this;
	}
	
	/**
	 * Add to the limit clause. Limit does not need a context.
	 */
	public function limit($start, $offset = NULL) {
		$array = NULL === $offset ? array($start) : array($start, $offset);
		$this->_predicates['limit'] = $array;
		return $this;
	}
	
	/**
	 * Change the context.
	 */
	public function order() { return $this->setContext('order'); }
	public function where() { return $this->setContext('where'); }
	public function group() { return $this->setContext('group'); }
	
	/**
	 * Special case for searching. There are two search operators that work
	 * in conjunction. SEARCH <field list> FOR <value>.
	 */
	public function search() {
		$this->setContext('search');
		$this->addOperator('search');
	}
	
	/**
	 * Take us out of the search context and perform a bit of magic :D
	 */
	public function for($val) {
		$this->setContext('where');
		
		// add in the special search predicate
		$this->_predicates[] = array(self::OPERATOR, 'search', array(
			'fields' => $this->_predicates['search'],
			'value' => $val,
		));
		
		// reset the search context
		$this->_predicates['search'] = array();
		
		return $this;
	}
	
	/**
	 * Add in predicates. This uses the shunting algorithm for dealing with
	 * operators.
	 */
	public function __call($fn, array $args = array()) {
		$fn_lower = strtolower($fn);
		
		// parse in the operator
		if(isset($this->_operators[$fn_lower])) {
			
			// special case, NOT is prefix notation
			if($fn_lower === 'not' || $fn_lower === 'like')
				return $this->addOperator($fn_lower);
			
			// the incoming operator precedence
			$ops = $this->_operators;
			$p = $ops[$fn];
			
			// stack is empty, add the operator to the predicates
			if($this->isEmpty())
				$this->addOperator($fn_lower);
			
			// stack is empty, deal with operators
			else {
				while(!$stack->isEmpty() && $ops[$this->top()] >= $p)
					$this->addOperator($stack->pop());
				
				// push the operator onto the stack
				$this->push($fn_lower);
			}
			
			// was there also an argument?
			if(!empty($args))
				$this->addOperand(NULL, $args[0]);
			
		// assume it's an aliased operand, the function name is the alias and
		// $args[0] is the field
		} else if(!empty($args))
			return $this->addOperand($fn, $args[0]);
		
		return $this;
	}
	
	/**
	 * Add an operand of some sort, this will usually be a keyword of some
	 * sort such as ASC or DESC.
	 */
	public function __get($key) {
		return $this->addOperand(strtolower($key), NULL);
	}
	
	/**
	 * Special case for a keyed substitute value.
	 */
	public function _($key) {
		$this->_context[] = array(self::SUBSTITUTE, $key, NULL);
		return $this;
	}
	
	/**
	 * Get the predicates, and make sure to finish off anything still left
	 * on the stack.
	 */
	public function getPredicates() {
		
		// close off the shunting algorithm
		while(!$this->isEmpty())
			$this->addOperator($this->pop());
		
		return $this->_predicates;
	}
}
