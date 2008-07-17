<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * where(void) <==> (new QueryPredicates)->where(void)
 */
function where() {
	$predicates = new QueryPredicates;
	return $predicates->where();
}

/**
 * limit(int $start[, int $offset]) <==> (new QueryPredicates)->limit($start, $offset)
 */
function limit($start, $offset = NULL) {
	$predicates = new QueryPredicates;
	return $predicates->limit($start, $offset);
}

/**
 * order(void) <==> (new QueryPredicates)->order(void)
 */
function order() {
	$predicates = new QueryPredicates;
	return $predicates->order();
}

/**
 * group(void) <==> (new QueryPredicates)->group(void)
 */
function group() {
	$predicates = new QueryPredicates;
	return $predicates->group();
}

/**
 * Class representing the various types of predicates that a PQL query can
 * have. Instances of this class are usually automatically created and returned
 * by means of Query instances; however, an instance can be created and directly
 * passed to named model gateways.
 *
 * @example
 *     Instance of Query that creates an instance of QueryPredicates. The
 *     instanceof QueryPredicates is surrounded in { and } to show where it
 *     starts and ends:
 *         from('model_name')->select(ALL)->where(){->id->neq->_};
 *     
 *     Instance of QueryPredicates being passed directly into a model gateway:
 *         Not Valid: $gateway->selectAll(limit(10));
 *         Valid:     $gateway->posts->selectAll(limit(10));
 *         
 *         Not Valid: $gateway->selectAll(where()->created_time->gt->_);
 *         Valid:     $gateway->posts->selectAll(where()->created_time->gt->_);
 *
 * @author Peter Goodman
 */
class QueryPredicates extends StackOfStacks {
	
	// an array of sets of predicates
	protected $_contexts, // the predicates are stored in reverse-polish
	          $_operands,
	          $_query,
	          $_context,
	          $_values,
	          $_compiled = FALSE;
	
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
		'eq' => 3, 'is' => 3, 'leq' => 3, 'geq' => 3, 
		'lt' => 3, 'gt' => 3, 'neq' => 3,
		
		// boolean
		'or' => 2, 'xor' => 2,
		'and' => 1,

		// pseudo operators that have actual functions
		'search' => -1, 'with' => -1,
	);
	
	// predicate types
	const OPERAND = 1,
	      OPERATOR = 2,
	      SUBSTITUTE = 4,
	      IMMEDIATE = 8,
	      REFERENCE = 16;
	
	/**
	 * QueryPredicates([Query])
	 *
	 * Set up the predicate contexts (where, order, group, limit, search) and 
	 * associate the instance with a Query object (if one is passed in).
	 */
	public function __construct(Query $query = NULL) {
		
		// the predicates table
		$this->_contexts = array(
			'where' => array(),
			'order' => array(),
			'group' => array(),
			'limit' => array(),
			'search' => array(), // a pseudo context
		);
		
		$this->_query = $query;
		$this->changeContext('where');
	}
	
	/**
	 */
	public function __destruct() {
		unset(
			$this->_context,
			$this->_contexts,
			$this->_operands,
			$this->_query,
			$this->_values
		);
	}
	
	/**
	 * $p->setQuery(Query) -> void
	 *
	 * Set/change the current query these predicates work for.
	 */
	public function setQuery(Query $query) {
		
		// if this is a change in query then this predicates object can no
		// longer be considered to be compiled (regardless of if it was or 
		// wasn't)
		if($this->_query !== NULL)
			$this->_compiled = FALSE;
		
		$this->_query = $query;
	}
	
	/**
	 * $p->setCompiled(void) -> void
	 *
	 * Tell the query that it has been compiled.
	 */
	public function setCompiled() {
		$this->_compiled = TRUE;
	}
	
	/**
	 * $p->isCompiled(void) -> bool
	 *
	 * Check if this query has been compiled.
	 */
	public function isCompiled() {
		return $this->_compiled;
	}
	
	/**
	 * $p->getQuery(void) -> {void, Query}
	 *
	 * Get the query that this QueryPredicates instances links to. If this
	 * instances is not linked to any query then NULL will be returned.
	 */
	public function getQuery() {
		return $this->_query;
	}
	
	/**
	 * $p->getContext(string $context) -> array
	 *
	 * Return the list (array) of predicates in reverse-polish notation.
	 */
	public function getContext($context) {
		$this->addTrailingOperators();
		return $this->_contexts[$context];
	}
	
	/**
	 * $p->contextIsEmpty(void) -> bool
	 *
	 * Check if there are zero predicates in the current context. If no context
	 * is currently set then the result will be TRUE.
	 */
	public function contextIsEmpty() {
		return empty($this->_context);
	}
	
	/**
	 * $p->changeContext(string $context) -> QueryPredicates
	 *
	 * Set which context {where, order, limit, group, search} of the query is
	 * currently being appended to.
	 *
	 * @internal
	 */
	public function changeContext($context) {
		$this->_compiled = FALSE;
		$this->addTrailingOperators();
		$this->_context = &$this->_contexts[$context];
		return $this;
	}
	
	/**
	 * $p->addTrailingOperators(void) -> void
	 *
	 * Add in any operators left on the context stack into the context's list
	 * of predicates.
	 *
	 * @internal
	 */
	public function addTrailingOperators() {
		while(!$this->isEmpty())
			$this->addOperator($this->pop());
	}
	
	/**
	 * $p->addOperand(string $key, mixed $value) -> QueryPredicates
	 *
	 * Append an operator onto the list of predicates. Operand types that this
	 * method supports are SUBSTITUTE values (value === _) and OPERAND.
	 *
	 * @internal
	 */
	protected function addOperand($key, $value) {
		$this->_compiled = FALSE;
		$type = ($value === _) ? self::SUBSTITUTE : self::OPERAND;
		$this->_context[] = array($type, $key, $value);
		return $this;
	}
	
	/**
	 * $p->addOperator(string $key) -> QueryPredicates
	 *
	 * Append an operator to the predicates list. Operators are only really for
	 * the conditions list.
	 * 
	 * @see QueryPredicates::parseOperator(...)
	 */
	protected function addOperator($key) {
		$this->_compiled = FALSE;
		$this->_context[] = array(self::OPERATOR, $key, NULL);
		return $this;
	}
	
	/**
	 * $p->addReference([string $model], string $field) -> QueryPredicates
	 *
	 * Add an operand that is a reference to a model's field. The model name
	 * can be null, in which case the query compiler will usually assume the
	 * first (and hopefully only) model in the list of sources.
	 *
	 * @internal
	 */
	protected function addReference($model = NULL, $field) {
		$this->_compiled = FALSE;
		$this->_context[] = array(self::REFERENCE, $model, $field);
		return $this;
	}
	
	/**
	 * $p->limit(int $limit[, int $offset]) -> QueryPredicates
	 *
	 * Set the limit context on the query.
	 *
	 * @note Each call to this method overwrites all previous calls.
	 */
	public function limit($limit, $offset = NULL) {		
		$this->_contexts['limit'] = array();
		$this->changeContext('limit');
		
		$limit = abs($limit);
		
		// the limit is to be substituted
		if($limit === _) 
			$this->_(NULL);
		
		// immediate value is passed as the limit
		else 
			$this->imm((int)$limit);
		
		if($offset !== NULL) {
			$this->addOperand(NULL, 'offset');
			if($offset === _) $this->_(NULL); else $this->imm($offset);
		}		
		
		return $this;
	}
	
	/**
	 * $p->order(void) -> QueryPredicates
	 *
	 * Change the query context to 'order'.
	 */
	public function order() { return $this->changeContext('order'); }
	
	/**
	 * $p->where(void) -> QueryPredicates
	 *
	 * Change the query context to 'where'.
	 */
	public function where() { return $this->changeContext('where'); }
	
	/**
	 * $p->group(void) -> QueryPredicates
	 *
	 * Change the query context to 'group'.
	 */
	public function group() { return $this->changeContext('group'); }
	
	/**
	 * $p->search(void) -> QueryPredicates
	 *
	 * Special case for searching. There are two search operators that work
	 * in conjunction. SEARCH <field list> WITH <value>.
	 *
	 * @see QueryPredicates::with(...)
	 */
	public function search() {
		$this->changeContext('search');
		$this->addOperator('search');
		
		return $this;
	}
	
	/**
	 * $p->with(mixed $search_term) -> QueryPredicates
	 *
	 * Take us out of the search context and perform a bit of magic to build
	 * a useful search predicate list. This predicate list does not follow the
	 * usual format that the other contexts do.
	 *
	 * @see QueryPredicates::search(...)
	 */
	public function with($search_term) {
		$this->changeContext('where');
		
		// add in the special search predicate
		$this->_context[] = array(self::OPERATOR, 'search', array(
			'fields' => $this->_contexts['search'],
			'value' => $search_term,
		));
		
		// reset the search context
		$this->_contexts['search'] = array();
		
		return $this;
	}
	
	/**
	 * $p->parseOperator(string $op[, array $args]) -> bool
	 * 
	 * Attempt to parse $op as an operator. If the string value of $op exists
	 * as a registered operator then this method uses the Dijkstra's Shunting
	 * algorithm to add it to the predicates list.
	 *
	 * @internal
	 */
	protected function parseOperator($op, array $args = NULL) {
		$op = strtolower($op);
		$ops = self::$_operators;
		
		$this->_compiled = FALSE;
		
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
	 * $p->__call(string $fn[, array $args]) <==> $d->$fn(...) -> QueryPredicates
	 *
	 * This method attempts to add $fn as an operator. If it fails to do so
	 * them it assumes that it is a model reference, where $fn is the model
	 * name and $args[0] is the field name.
	 *
	 * @see QueryPredicates::parseOperator(...)
	 * @see QueryPredicates::addReference(...)
	 */
	public function __call($fn, array $args = array()) {
		
		// assume it's an aliased operand, the function name is the alias and
		// $args[0] is the field
		if(!$this->parseOperator($fn, $args) && !empty($args))
			return $this->addReference($fn, $args[0]);
		
		return $this;
	}
	
	/**
	 * $p->__get(string $key) <==> $p->$key -> QueryPredicates
	 *
	 * First, check if $key can be parsed as an operator. If not, it looks for
	 * special cases where $key belongs to {asc, desc, having} and adds one of
	 * those as a special operator. If $key ends up not being an operator after 
	 * all then $key is added as the field in a reference with a NULL model.
	 *
	 * @note A $key of '_' is handled as a substitute value.
	 * @see QueryPredicates::parseOperator(...)
	 * @see QueryPredicates::addReference(...)
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
	 * $p->_([string $key]) -> QueryPredicates
	 *
	 * Create a substitute value. If $key is a string then it will act as a
	 * keyed substitute value.
	 */
	public function _($key = NULL) {
		
		// not allowed to do this :P
		if($key === _) {
			throw new InvalidArgumentException(
				"Cannot used substitute value as a keyed substitute."
			);
		}
		
		$this->_compiled = FALSE;
		$this->_context[] = array(self::SUBSTITUTE, $key, NULL);
		return $this;
	}
	
	/**
	 * $p->imm(mixed $val) -> QueryPredicates
	 *
	 * Add an immediate constant to the query. Immediate constants belong to
	 * the set {NULL, int, string, bool}.
	 *
	 * @note If a substitute value is passed in then the immediate will be
	 *       treated as a substitute value instead.
	 * @see QueryPredicates::_(...)
	 */
	public function imm($val) {
		
		if($val === _)
			return $this->_(NULL);
		
		$this->_compiled = FALSE;
		$this->_context[] = array(self::IMMEDIATE, NULL, $val);
		return $this;
	}
	
	/**
	 * $p->merge([QueryPredicates]) -> QueryPredicates
	 *
	 * Merge another QueryPredicates object into this one.
	 */
	public function merge(QueryPredicates $predicates = NULL) {
		
		if($predicates === NULL)
			return $this;
		
		// go over each context and either merge the foreign predicate context
		// in (where) or replace with the foreign context data (everything
		// else).
		foreach($this->_contexts as $context => &$data) {
			$foreign_data = $predicates->getContext($context);
			
			// skip. no point
			if(empty($foreign_data))
				continue;
			
			// incoming data is appended onto existing data
			if($context == 'where') {
				$this->changeContext($context);
				
				if(!empty($data))
					$this->parseOperator('and');
				
				$data = array_merge($data, $foreign_data);
				
			// all other existing is replaced by incoming data
			} else
				$data = $foreign_data;
				
		}
		
		return $this;
	}
}
