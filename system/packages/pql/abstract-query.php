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
 * A language to query a hypothetical data structure.
 * @author Peter Goodman
 */
abstract class AbstractQuery {
    
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
	const GROUP_BY = 2048,
		  ORDER_BY = 4096,
		  ORDER_ASC = 4097, // these two don't follow the usual <<
		  ORDER_DESC = 4098,
		  LIMIT	   = 8192,
		  WHERE    = 16384;
	
    // operator or operand?
    const OP_OPERATOR = 1,
          OP_OPERAND  = 2;
    
    // operand types
    const VALUE_CONSTANT  = 4, // immediate constants: strings, integers, etc.
          VALUE_REFERENCE = 8; // equivalent of sql columns / fields
    
    protected $stack;
	
    public $aliases      = array(), // maps aliases to source names
           $sources      = array(), // the data sources being used
           $items        = array(), // what do we want to find from each 
                                    // data source?
           $item_aliases = array(), // aliases => items
           $counts       = array(), // things in the datasources to count
           $values       = array(), // values for a create / delete / update
		   $relations    = array(),
           $predicates   = array(); // conditions to be met on the data 
                                    // returned
    
    // simplified mappings of some possible operators
    static protected $complex_ops;
    
    /** 
     * Constructor, very little to set up.
     */
    public function __construct() {
        
		$this->stack = new Stack;
		
        // set the operator mappings
        if(NULL === self::$complex_ops) {
            self::$complex_ops = array(
                self::LOG_NOT | self::LOG_LT => self::LOG_GT | self::LOG_EQ,
                self::LOG_NOT | self::LOG_GT => self::LOG_LT | self::LOG_EQ,
                self::LOG_NOT | self::LOG_LT | self::LOG_EQ => self::LOG_GT,
                self::LOG_NOT | self::LOG_GT | self::LOG_EQ => self::LOG_LT,
            );
        }
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
        $this->stack->push($alias);
    }
    
    /**
     * If the supplied datasource isn't available, create it. If it is, switch
     * to it.
     */
    public function setDataSource($ds, $alias = NULL) {
        
        // pop whatever is on the stack off
        $this->stack->silentPop();
        
        // no alias, default to casting the datasource to a string
        if(NULL === $alias)
            $alias = (string)$ds;
        
        // this data source doesn't yet exist in the query, add it
        if(!isset($this->sources[$alias]))
            $this->addDataSource($ds, $alias);
        
        // this data source exists, push its alias the stack
        else
            $this->stack->push($alias);
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
        $alias = $this->stack->top();
        $this->addTo($this->items, $alias, $items);
    }
    
    /**
     * Tell the datasource that we'll be counting something. This is sort of
     * a special case as it affects the types of queries that will be built
     * from this class.
     */
    public function addCount(array $items = array()) {
        $alias = $this->stack->top();
        $this->addTo($this->counts, $alias, $items);
    }

	/**
	 * Add to the values array.
	 */
	public function addValues(array $values = array()) {
		$alias = $this->stack->top();
		
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
}

/**
 * This class just builds up query information; however, it's not up to this 
 * class to know what type of query it is. In fact, the class is simply
 * data. Given this, there are no defined 'update' or 'delete' methods.
 * @author Peter Goodman
 */
class AbstractQueryLanguage extends AbstractQuery {
	
	// table mapping operator names to their values
	static protected $operator_names;
	
	/**
	 * Constructor, set up the operator table if it hasn't been yet.
	 */
	public function __construct() {
		
		// a list of basic operators. note: operators can also be concatenated
		// with underscores through __get and __call
		if(!self::$operator_names) {
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
		}
		parent::__construct();
	}
	
	/**
	 * Parse variable requests, these are usually operators.
	 */
	public function __get($op) {
		$this->parseOperator($op);

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
				
				// is this an aliased item?
				if(isset($this->sources[$fn])) {
					$this->addOperand(
						parent::VALUE_REFERENCE, 
						array($fn, $args[0])
					);
					
				// is this an operator+value?
				} else if($this->parseOperator($fn))
					$this->addOperand(
						parent::VALUE_CONSTANT, 
						$fn == 'limit' ? $args : $args[0]
					);

				break;
		}
		
		return $this;
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
}

