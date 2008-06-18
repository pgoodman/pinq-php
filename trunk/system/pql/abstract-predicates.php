<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists('where')) {
	
	function &pql_create_predicates($pred_type, AbstractQuery $query = NULL, $class = 'AbstractSingleSourcePredicates') {
		$predicates = new $class($query);
		$predicates->addOperator($pred_type);
		return $predicates;
	}
	
	function &where() {
		return pql_create_predicates(AbstractPredicates::WHERE);
	}
	
	function &limit() {
		$args = func_get_args();
		$predicates = pql_create_predicates(AbstractPredicates::LIMIT);
		$predicates->addOperand(AbstractPredicates::VALUE_CONSTANT, $args);
		return $predicates;
	}
	
	function &order() {
		return pql_create_predicates(AbstractPredicates::ORDER_BY);
	}
	
	function &group() {
		return pql_create_predicates(AbstractPredicates::GROUP_BY);
	}
}

/**
 * Build up a list of predicates to use in an abstract query.
 * @author Peter Goodman
 */
class AbstractPredicates {
	
	// predicate table for logical comparison operators
	const LOG_EQ  = 1, // equality
		  LOG_GT  = 2,
		  LOG_LT  = 4,
		  LOG_NOT = 8, // special one, needs to be used with another
		  LOG_IS  = 16; // identity, in some cases the same as equality
	
	// predicate table for more complicated types, these need to be
	// implemented by whatever is building the queries
	const LOG_IN  = 32, // one thing is in another
		  LOG_HAS = 64; // one thing matches (parts of) another
		
	// predicates for binary AND and OR
	const LOG_AND = 128,
		  LOG_OR  = 256;
	
	// signals to open/close parens
	const OPEN_SUBSET  = 512,
		  CLOSE_SUBSET = 1024;
	
	// other random things
	const GROUP_BY   = 2048,
		  ORDER_BY   = 4096,
		  ORDER_ASC  = 4097, // these two don't follow the usual <<
		  ORDER_DESC = 4098,
		  LIMIT	     = 8192,
		  WHERE	     = 16384;
	
	// special operator for linking two models in a where using values but
	// without knowing what fields are being pivoted on.
	//const PIVOT = 32768;
	
	// operator or operand?
	const OP_OPERATOR = 1,
		  OP_OPERAND  = 2;
	//	  OP_PSEUDO   = 4;
	
	// operand types
	const VALUE_CONSTANT  = 4, // immediate constants: strings, integers, etc.
		  VALUE_REFERENCE = 8; // equivalent of sql columns / fields

	
	protected $predicates = array(), // array of predicates
	          $query; // the AbstractQuery object
	
	// mappings of some possible operators to simpler versions of themselves
	// and an array mapping operator names to their values.
	static protected $complex_ops,
	                 $operator_names;
	
	/**
	 * Constructor, bring in a reference to the query's sources array.
	 */
	public function __construct(AbstractQuery $query = NULL) {
		
		// a list of basic operators. note: operators can also be concatenated
		// with underscores through __get and __call
		if(NULL === self::$operator_names) {
			
			self::$operator_names = array(
				'eq'  => self::LOG_EQ,
				'neq' => self::LOG_EQ | self::LOG_NOT,
				
				'gt'   => self::LOG_GT,
				'gteq' => self::LOG_GT | self::LOG_EQ,
				
				'lt'   => self::LOG_LT,
				'lteq' => self::LOG_LT | self::LOG_EQ,
				
				// the eq in here is to make it easy for implementations that
				// don't support identity
				'is'   => self::LOG_IS | self::LOG_EQ,
				'isnt' => self::LOG_IS | self::LOG_NOT | self::LOG_EQ,
				
				'and'  => self::LOG_AND,
				'nand' => self::LOG_AND | self::LOG_NOT,
				
				'or'  => self::LOG_OR,
				'nor' => self::LOG_OR | self::LOG_NOT,
				
				'not' => self::LOG_NOT,
				
				'open'	=> self::OPEN_SUBSET,
				'close' => self::CLOSE_SUBSET,
				
				'group' => self::GROUP_BY,
				'limit' => self::LIMIT,
				'sort' => self::ORDER_BY,
				'asc' => self::ORDER_ASC,
				'desc' => self::ORDER_DESC,
				
				'where' => self::WHERE,
			);
			
			// set the operator mappings
			self::$complex_ops = array(
				self::LOG_NOT | self::LOG_LT => self::LOG_GT | self::LOG_EQ,
				self::LOG_NOT | self::LOG_GT => self::LOG_LT | self::LOG_EQ,
				self::LOG_NOT | self::LOG_LT | self::LOG_EQ => self::LOG_GT,
				self::LOG_NOT | self::LOG_GT | self::LOG_EQ => self::LOG_LT,
			);
		}
		
		$this->query = $query;
	}
	
	/**
	 * Destructor.
	 */
	public function __destruct() {
		unset($this->predicates);
	}
	
	/**
	 * Parse out an operator and add it to the predicates.
	 * @internal
	 */
	protected function parseOperator($op) {

		$ops = explode('_', strtolower($op));
		$operator = 0;
		
		// accumulate the operations
		foreach($ops as $op) {
			
			// operator doesn't exist or is there for semantics alone, skip
			if(!isset(self::$operator_names[$op]) || $op == 'by')
				continue;
			
			// what is the "value" of this operator?
			$add = self::$operator_names[$op];
			
			// negate previously used NOT operators
			if($add & self::LOG_NOT && $operator & self::LOG_NOT)
				$operator = $operator & ~self::LOG_NOT;
			
			// otherwise, OR in the operator
			else
				$operator = $operator | $add;
		}
		
		// we have successfully accumulated an operator
		if($operator > 0) {
			$this->addOperator($operator);
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Add an operand to the query.
	 */
	public function addOperand($type, $value) {
		// add this operand to the predicates array
		$this->predicates[] = array(self::OP_OPERAND | $type, $value);
	}
	
	/**
	 * Add a condition that need to be met for data to be returned. Note:
	 * $operator could actually be the combination of many operators, such as
	 * not greater than or equal to. Although that is redundant, it is allowed.
	 */
	public function addOperator($operator) {
		
		// lets see if we can simplify the operator
		if(isset(self::$complex_ops[$operator]))
			$operator = self::$complex_ops[$operator];
		
		// add this operator to the predicates array
		$this->predicates[] = array(self::OP_OPERATOR, $operator);
	}
	
	/**
	 * Add a predicate operand/operator.
	 */
	public function __call($fn, array $args = array()) {
		
		$fn = strtolower($fn);
			
		// is this an operator+value?
		if($this->parseOperator($fn)) {
						
			$this->addOperand(
				self::VALUE_CONSTANT, 
				($fn === 'limit') ? $args : $args[0]
			);
		
		// assume it's an aliased item. we don't actually need to check if
		// this is right because, for all intents and purposed, the query will
		// fail if it's wrong.
		} else {
			$this->addOperand(
				self::VALUE_REFERENCE, 
				array($fn, $args[0])
			);
		}
		
		return $this;
	}
	
	/**
	 * Add a predicate that takes no operands.
	 */
	public function __get($operator) {
		$this->parseOperator($operator);		
		return $this;
	}
	
	/**
	 * Return the predicates array.
	 * @internal
	 */
	public function getPredicates() {
		return $this->predicates;
	}
}

/**
 * A simplified predicate notation for when an abstract query only has a
 * single data source. Instead of __call being used to identify table sources
 * it is now used as the operator itself, and the arguments are the operands.
 * @author Peter Goodman
 */
class AbstractSingleSourcePredicates extends AbstractPredicates {
	
	/**
	 * Call an operator and pass in its operands. We assume that the first
	 * operand is a reference to a field in the abstract model and nothing 
	 * else.
	 */
	public function __call($op, array $operands = array()) {
		
		// special case for a LIMIT operator which can accept an arbitrary
		// number of values
		$op = strtolower($op);
		
		if($op == 'limit') {
			$this->addOperator(self::LIMIT);
			$this->addOperand(self::VALUE_CONSTANT, $operands);
		
		// normal operator
		} else {
			$this->addOperand(parent::VALUE_REFERENCE, $operands[0]);
			$this->parseOperator($op);
			$this->addOperand(parent::VALUE_CONSTANT, $operands[1]);
		}
		
		return $this;
	}
}
