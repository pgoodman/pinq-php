<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * And abstract class for something to build a "concrete" thing, such as an SQL
 * statement, out of an abstract query.
 * @author Peter Goodman
 */
abstract class QueryCompiler implements Compiler {
	
	// query types
	const SELECT = 1,
	      CREATE = 2,
	      MODIFY = 4,
	      DELETE = 8;
		
	// the query and models
	protected $query,
	          $models,
	          $relations;
	
	/**
	 * Constructor, bring in the query and models.
	 */
	public function __construct(Dictionary $models, ModelRelations $relations) {
		$this->models = $models;
		$this->relations = $relations;
	}
	
	/**
	 * Destructor, break references.
	 */
	public function __destruct() {
		unset(
			$this->query,
			$this->models,
			$this->relations
		);
	}
	
	/**
	 * Set a new query to be used for compilation.
	 */
	public function setQuery(Query $query) {
		$this->query = NULL; // unset the old one
		$this->query = $query;
	}
	
	/**
	 * Models are all aliased. We allow for the models to also have internal
	 * names but they aren't required. Thus, this function, given an query
	 * model alias will try to find the model name (alias), and if the
	 * associated model has an internal name it will return that otherwise it
	 * will return the model alias.
	 * @internal
	 */
	protected function getAbsoluteModelName($model_alias) {
		return $this->getDefinitionByModelAlias($model_alias)->getInternalName();
	}
	
	/**
	 * Get a model given a model alias.
	 */
	protected function getDefinitionByModelAlias($model_alias) {
		return $this->models[
			$this->query->getUnaliasedModelName($model_alias)
		];
	}
	
	/**
	 * Predicates compiler. This essentially turns things back into infix from
	 * postfix.
	 * @internal
	 */
	public function compilePredicates($context) {
		
		if(NULL === ($predicates = $this->query->getPredicates()))
			return NULL;
		
		$predicate_context = $predicates->getContext($context);
		
		if(empty($predicate_context))
			return;
		
		$stack = new Stack;
		
		// go over the predicates from left to right
		foreach($predicate_context as $operation) {
			
			if(!is_array($operation))
				continue;
			
			// operators
			if($operation[0] & QueryPredicates::OPERATOR) {
					
				$operator = $operation[1];
				
				// prefix, special cases
				if($operator == 'like' || $operator == 'not') {
					
					$stack->push($this->compilePrefixOperator(
						$operator,
						$stack->pop()
					));
					
				// special search operator
				} else if($operator == 'search') {
				
					$stack->push($this->compileSearchOperator(
						$operation
					));
				
				// infix
				} else {
					$right = $stack->pop();					
					$stack->push($this->compileInfixOperator(
						$operator,
						$stack->pop(),
						$right
					));
				}
					
			// operands	
			} else {
				$stack->push($this->compileOperand($operation));
			}
		}
				
		return $stack->isEmpty() ? NULL : $stack->toArray();
	}
	
	/**
	 * Compile relations as predicates in the where context.
	 * 
	 * TODO: This actually adds to the predicates list of the query. That
	 *       means that the query should only be used once per request unless
	 *       it is properly used with substitute values and aliases.
	 */
	public function compileRelationsAsPredicates() {
		
		$query = $this->query;
		$num_sources = count($query->getContexts());
		$relations = $query->getRelations();
		
		// no relations, don't do anything useless
		if(empty($relations))
			return;
		
		// get the predicates and set the context or create a new set of
		// predicates if the original predicates were null
		($predicates = $query->getPredicates())
		 and ($predicates = $predicates->where())
		 or ($predicates = where());
		
		// should we being by joining with AND?
		$join_with_and = !$predicates->contextIsEmpty();
		
		// isolate ALL of these predicates
		$predicates->in;
		
		// go over the relations
		foreach($relations as $left_alias => $related_aliases) {
			
			$left_name = $query->getUnaliasedModelName($left_alias);
			
			foreach($related_aliases as $right_alias) {
				
				// find a path between two models	
				$path = $this->relations->getPath(
					$left_name, 
					$query->getUnaliasedModelName($right_alias),
					$this->models
				);
				
				// only allow direct relationships
				$count = count($path);
				if($count > 2) {
					throw new DomainException(
						"PQL modify query only allows direct relationships ".
						"to be satisfied."
					);
				}
				
				// put on a prefixing and
				if($join_with_and) $predicates->and;
				
				// isolate this specific relation are add in the predicates
				$predicates->in
				           ->$left_alias($path[0][1])->eq
				           ->$right_alias($path[1][1])
				           ->out;
				
				// if we're making more relations after this one then they
				// will all need to be joined with an AND
				$join_with_and = TRUE;
			}
		}
		
		$predicates->out;
		
		// set the predicates to the query if they were empty/changed
		$query->setPredicates($predicates);
	}
	
	/**
	 * Compile a certain type of query.
	 */
	public function compile($flags = 0) {
		
		switch($flags) {
			case self::SELECT:
				return $this->compileSelect();
			
			case self::MODIFY:
				return $this->compileUpdate();
			
			case self::CREATE:
				return $this->compileInsert();
			
			case self::DELETE:
				return $this->compileDelete();
		}
	}
	
	/**
	 * Compile an operand.
	 * @internal
	 */
	protected function compileOperand(array $op) {
		
		// substitute key or value		
		if($op[0] & QueryPredicates::SUBSTITUTE)
			return $this->compileSubstitute($op[1]);
		
		// reference to a model/field
		else if($op[0] & QueryPredicates::REFERENCE)
			return $this->compileReference($op[2], $op[1]);
		
		// if we've found a straight operand type
		else if($op[0] & QueryPredicates::OPERAND)
			return $this->compileLiteral($op[2]);
		
		// immediate constant
		else
			return $this->compileImmediate($op[2]);
	}
	
	/**
	 * Abstract methods.
	 */
	
	// operands
	abstract protected function compileReference($field, $model = NULL);
	abstract protected function compileSubstitute($key);
	abstract protected function compileLiteral($value);
	abstract protected function compileImmediate($value);
	
	// operators
	abstract protected function compilePrefixOperator($operator, $operand);
	abstract protected function compileInfixOperator($operator, $op1, $op2);
	abstract protected function compileSearchOperator(array $search);
	
	// query types
	abstract protected function compileSelect();
	abstract protected function compileUpdate();        
	abstract protected function compileInsert();   
	abstract protected function compileDelete();
}
