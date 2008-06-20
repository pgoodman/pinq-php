<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists('where')) {
	
	function where() {
		$predicates = new QueryPredicates;
		return $predicates->where();
	}
	
	function limit($start, $offset = NULL) {
		$predicates = new QueryPredicates;
		return $predicates->limit($start, $offset);
	}
	
	function order() {
		$predicates = new QueryPredicates;
		return $predicates->order();
	}
	
	function group() {
		$predicates = new QueryPredicates;
		return $predicates->group();
	}
}

/**
 * Build up predicates for a PQL query.
 * @author Peter Goodman
 */
class QueryPredicates extends StackOfStacks {
	
	// an array of sets of predicates
	protected $_predicates, // the predicates are stored in reverse-polish
	          $_operands,
	          $_query,
	          $_context,
	          $_values;
	
	// operators and their precedence levels. high precedence level means
	// that the operator is more difficult to tear apart
	static protected $_operators = array(
		// braces
		'in' => 7, 'out' => 7,

		// prefix operators
		'not' => 6, 'like' => 6,

		// arithmetic
		'mul' => 5, 'div' => 5,
		'add' => 4, 'sub' => 4,

		// comparison
		'eq' => 3, 'leq' => 3, 'geq' => 3, 'lt' => 3, 'gt' => 3, 'neq' => 3,
		'and' => 2, 'or' => 2, 'xor' => 2,

		// pseudo operators that have actual functions
		'search' => -1, 'with' => -1,
	);
		
	const OPERAND = 1,
	      OPERATOR = 2,
	      SUBSTITUTE = 4,
	      IMMEDIATE = 8,
	      REFERENCE = 16;
	
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
		
		$this->_query = $query;
		$this->setContext('where');
	}
	
	/**
	 * Set/change the current query these predicates work for.
	 */
	public function setQuery(Query $query) {
		$this->_query = $query;
	}
	
	/**
	 * Get the query that these predicates link to.
	 */
	public function getQuery() {
		return $this->_query;
	}
	
	/**
	 * Get the predicates, and make sure to finish off anything still left
	 * on the stack.
	 */
	public function getPredicates($context) {
		$this->addTrailingOperators();
		return $this->_predicates[$context];
	}
	
	/**
	 * Check if the context is empty.
	 */
	public function contextIsEmpty() {
		return empty($this->_context);
	}
	
	/**
	 * Set what part of the query we are modifying.
	 * @internal
	 */
	public function setContext($context) {
		$this->addTrailingOperators();
		$this->_context = &$this->_predicates[$context];
		return $this;
	}
	
	/** 
	 * Add in any operators left on the stack.
	 */
	public function addTrailingOperators() {
		while(!$this->isEmpty())
			$this->addOperator($this->pop());
	}
	
	/**
	 * Add an operand.
	 */
	protected function addOperand($key, $value) {
		$type = ($value === _) ? self::SUBSTITUTE : self::OPERAND;
		$this->_context[] = array($type, $key, $value);
		return $this;
	}
	
	/**
	 * Add an operator to the predicates list. Operators are only really for
	 * the conditions list.
	 */
	protected function addOperator($key) {
		$this->_context[] = array(self::OPERATOR, $key, NULL);
		return $this;
	}
	
	/**
	 * Add a model/field reference.
	 */
	protected function addReference($model, $field) {
		$this->_context[] = array(self::REFERENCE, $model, $field);
		return $this;
	}
	
	/**
	 * Add to the limit clause. Limit does not need a context.
	 */
	public function limit($start, $offset = NULL) {
		//$array = NULL === $offset ? array($start) : array($start, $offset);
		
		$this->_predicates['limit'] = array();
		$this->setContext('limit');
		
		// clear any previous stuff
		
		
		if($start === _) $this->_(NULL); else $this->imm($start);
		
		if($offset !== NULL) {
			$this->addOperand(NULL, 'offset');
			if($offset === _) $this->_(NULL); else $this->imm($offset);
		}		
		
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
	 * in conjunction. SEARCH <field list> WITH <value>.
	 */
	public function search() {
		$this->setContext('search');
		$this->addOperator('search');
	}
	
	/**
	 * Take us out of the search context and perform a bit of magic :D
	 */
	public function with($val) {
		$this->setContext('where');
		
		// add in the special search predicate
		$this->_context[] = array(self::OPERATOR, 'search', array(
			'fields' => $this->_predicates['search'],
			'value' => $val,
		));
		
		// reset the search context
		$this->_predicates['search'] = array();
		
		return $this;
	}
	
	/**
	 * Parse for an operator.
	 */
	public function parseOperator($op, array $args = NULL) {
		$op = strtolower($op);
		$ops = self::$_operators;
		
		// a opening brace is being used, push on a new stack
		if($op == 'in')
			$this->pushStack();
		
		// closing brace, add in remaining operators in the top stack and
		// pop it off
		else if($op == 'out') {
			$this->addTrailingOperators();
			$this->popStack();
			
		// try to parse a normal operator
		} else if(isset($ops[$op])) {
			
			// the incoming operator precedence
			$p = $ops[$op];
			
			// while there are operators on the stack whose precedence is >=
			// to the precedence of the operator we're trying to add, pop them
			// off the stack and add them to the predicates list
			while(!$this->isEmpty() && $ops[$this->top()] >= $p)
				$this->addOperator($this->pop());
			
			// push the operator onto the stack
			$this->push($op);
			
			// was there also an argument?
			if(!empty($args)) {
				
				// add in the substitute
				if($args[0] === _)
					$this->_(NULL);
				
				// add in the immediate constant
				else
					$this->imm($args[0]);
					
			}
		
		// we didn't find an operator, oh well
		} else {
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Add in predicates. This uses the shunting algorithm for dealing with
	 * operators.
	 */
	public function __call($fn, array $args = array()) {
		
		// assume it's an aliased operand, the function name is the alias and
		// $args[0] is the field
		if(!$this->parseOperator($fn, $args) && !empty($args))
			return $this->addReference($fn, $args[0]);
		
		return $this;
	}
	
	/**
	 * Add an operand of some sort, this will usually be a keyword of some
	 * sort such as ASC or DESC.
	 */
	public function __get($key) {
		if(!$this->parseOperator($key)) {
			
			if($key != '_') {
				$lower = strtolower($key);
				
				// special cases
				if($lower == 'asc' || $lower == 'desc' || $lower == 'having')
					$this->addOperand(NULL, $key);
				
				// assume it's a model/field reference
				else
					$this->addReference(NULL, $key);
			
			// substitiute
			} else
				$this->_(NULL);
		}
		
		return $this;
	}
	
	/**
	 * Special case for a keyed substitute value.
	 */
	public function _($key) {
		$this->_context[] = array(self::SUBSTITUTE, $key, NULL);
		return $this;
	}
	
	/**
	 * Put in an immediate value.
	 */
	public function imm($val) {
		
		if($val === _)
			return $this->_(NULL);
		
		$this->_context[] = array(self::IMMEDIATE, NULL, $val);
		return $this;
	}
}
