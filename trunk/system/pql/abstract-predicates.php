<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

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
	
	// operator or operand?
	const OP_OPERATOR = 1,
		  OP_OPERAND  = 2;
	
	// operand types
	const VALUE_CONSTANT  = 4, // immediate constants: strings, integers, etc.
		  VALUE_REFERENCE = 8; // equivalent of sql columns / fields

	public $predicates = array(), // array of predicates
	       $sources;
	
	// mappings of some possible operators to simpler versions of themselves
	// and an array mapping operator names to their values.
	static protected $complex_ops,
	                 $operator_names;
	
	/**
	 * Constructor, bring in a reference to the query's sources array.
	 */
	public function __construct(array &$sources) {
		
		$this->sources = &$sources;
		
		// a list of basic operators. note: operators can also be concatenated
		// with underscores through __get and __call
		if(NULL === self::$operator_names) {
			
			self::$operator_names = array(
				'eq'  => parent::LOG_EQ,
				'neq' => parent::LOG_EQ | parent::LOG_NOT,
				
				'gt'   => parent::LOG_GT,
				'gteq' => parent::LOG_GT | parent::LOG_EQ,
				
				'lt'   => parent::LOG_LT,
				'lteq' => parent::LOG_LT | parent::LOG_EQ,
				
				// the eq in here is to make it easy for implementations that
				// don't support identity
				'is'   => parent::LOG_IS | parent::LOG_EQ,
				'isnt' => parent::LOG_IS | parent::LOG_NOT | parent::LOG_EQ,
				
				'and'  => parent::LOG_AND,
				'nand' => parent::LOG_AND | parent::LOG_NOT,
				
				'or'  => parent::LOG_OR,
				'nor' => parent::LOG_OR | parent::LOG_NOT,
				
				'not' => parent::LOG_NOT,
				
				'open'	=> parent::OPEN_SUBSET,
				'close' => parent::CLOSE_SUBSET,
				
				'group' => parent::GROUP_BY,
				'limit' => parent::LIMIT,
				'order' => parent::ORDER_BY,
				'asc' => parent::ORDER_ASC,
				'desc' => parent::ORDER_DESC,
				
				'where' => parent::WHERE,
			);
			
			// set the operator mappings
			self::$complex_ops = array(
				self::LOG_NOT | self::LOG_LT => self::LOG_GT | self::LOG_EQ,
				self::LOG_NOT | self::LOG_GT => self::LOG_LT | self::LOG_EQ,
				self::LOG_NOT | self::LOG_LT | self::LOG_EQ => self::LOG_GT,
				self::LOG_NOT | self::LOG_GT | self::LOG_EQ => self::LOG_LT,
			);
		}
		
	}
	
	/**
	 * Parse out an operator and add it to the predicates.
	 * @internal
	 */
	protected function parseOperator($op) {
		$ops = explode('_', $op);
		$operator = 0;
		
		// accumulate the operations
		foreach($ops as $op) {
			
			$op = strtolower($op);
			
			// operator doesn't exist or is there for semantics alone, skip
			if(!isset(self::$operator_names[$op]) || $op == 'by')
				continue;
			
			// what is the "value" of this operator?
			$add = self::$operator_names[$op];
			
			// negate previously used NOT operators
			if($add & parent::LOG_NOT && $operator & parent::LOG_NOT)
				$operator = $operator & ~parent::LOG_NOT;
			
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
		
		// is this an aliased item?
		if(isset($this->sources[$fn])) {
			$this->addOperand(
				parent::VALUE_REFERENCE, 
				array($fn, $args[0])
			);
			
		// is this an operator+value?
		} else if($this->parseOperator($fn)) {
			$this->addOperand(
				parent::VALUE_CONSTANT, 
				$fn == 'limit' ? $args : $args[0]
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
		
		// in single-source mode, __call requires two arguments (operands)
		if(count($operands) < 2) {
			throw new BadFunctionCallException(
				"Query [{$op}] expected two operands."
			);
		}
		
		list($op_left, $op_right) = $operands;
		
		$this->addOperand(parent::VALUE_REFERENCE, $operands[0]);
		$this->parseOperator($op);
		$this->addOperand(parent::VALUE_CONSTANT, $operands[1]);
		
		return $this;
	}
}
