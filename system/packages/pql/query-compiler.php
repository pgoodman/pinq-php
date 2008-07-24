<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class to compile a PQL query into a form compatible for a datasource. For
 * example: the database query compiler compiles PQL into SQL.
 *
 * @author Peter Goodman
 */
abstract class PinqPqlQueryCompiler implements InstantiablePackage {
		
	// the query and models
	protected $query,
	          $models,
	          $relations,
	          $query_type;
	
	/**
	 * PinqPqlQueryCompiler(PinqModelDictionary, PinqModelRelationalMap)
	 */
	public function __construct(Dictionary $models, 
	            PinqModelRelationalMap $relations) {
		
		$this->models = $models;
		$this->relations = $relations;
	}
	
	/**
	 */
	public function __destruct() {
		unset(
			$this->query,
			$this->models,
			$this->relations
		);
	}
	
	/**
	 * $c->getInternalModelName(string $model_alias) -> string
	 *
	 * Get the internal name of a model given either its external name or an
	 * alias of its external name.
	 *
	 * @internal
	 */
	protected function getInternalModelName($model_alias) {
		$definition = $this->getDefinitionByModelAlias($model_alias);
		return $definition->getInternalName();
	}
	
	/**
	 * $c->getDefinitionByModelAlias(string $model_alias) -> PinqModelDefinition
	 *
	 * Get a model definition given its external name or an alias to its
	 * external name.
	 */
	protected function getDefinitionByModelAlias($model_alias) {
		return $this->models[
			$this->query->getUnaliasedModelName($model_alias)
		];
	}
	
	/**
	 * $c->compilePredicates(string $context {where,group,limit,order}) -> void
	 *
	 * Compile a part of the predicates. Query predicates are stored in reverse
	 * polish notation (postfix). This calls on various other specific compiling
	 * functions to build up a final predicates list.
	 *
	 * @internal
	 */
	protected function compilePredicates($context) {
		
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
	 * $c->flattenRelationsGraph(array) -> array
	 *
	 * Flatten the relations graph into the query.
	 */
	protected function flattenRelationsGraph(array $graph) {
		
		// flatten the graph
		$query = $this->query;
		$aliases = &$query->getAliases();
		
		$stack = new Stack;
		$stack->push($graph);
		$from_fields = array();
		
		while(!$stack->isEmpty()) {
			$models = $stack->pop();
			
			foreach($models as $model_alias => $dependencies) {
				
				// add in the from field to the query
				$from_fields[$model_alias] = array();
				$query->from($aliases[$model_alias], $model_alias);
				
				// flatting out the sub-dependencies
				if(!empty($dependencies))
					$stack->push($dependencies);
			}
		}
		
		return $from_fields;
	}
	
	/**
	 * $c->compileRelationsAsPredicates(void) -> void
	 *
	 * Compile relations between models into a list of predicates and join them
	 * into the query. This dramatically changes the way the query works.
	 *
	 * @note This assumes the relations have been flattened beforehand.
	 */
	protected function compileRelationsAsPredicates() {
		
		$query = $this->query;
				
		// get the graph of the joins that need to be made
		$aliases = &$this->query->getAliases();
		$relations = &$this->query->getRelations();
		
		// no relations, don't do anything useless
		if(count($relations) <= 1)
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
			
			// skip, we don't need to do anything
			if(empty($related_aliases))
				continue;
			
			foreach($related_aliases as $right_alias) {
				
				// if we're doing a self-join of sorts using the same alias it
				// means joining would be useless, and possibly give unexpected
				// results (eg: self.parent_id->self.id).
				if($left_alias === $right_alias)
					continue;
				
				$right_name = $query->getUnaliasedModelName($right_alias);
				
				// find a path between two models	
				$path = $this->relations->getPath(
					$left_name, 
					$right_name,
					$this->models
				);
				
				if(2 < ($count = count($path))) {
					throw new DomainException(
						"PQL modify query only allows direct relationships ".
						"to be satisfied. Relationship between [{$left_name}] ".
						"and [{$right_name}] is indirect."
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
		
		// remove all relations from the query, they are no longer needed and
		// should be ignored. this will make it such that future calls to this
		// method to no add to the changes that we've made
		$relations = array();
	}
	
	/**
	 * $c->compilePivots(void) -> void
	 *
	 * Rebuild pivots. Pivots are when we link two tables together in a query
	 * and then want to add an extra predicate so that we can specify a value
	 * to "pivot" the join tables on.
	 *
	 * Note: this function is called within rebuildPredicates() as it usually
	 *       modifies the predicates array significantly.
	 * 
	 * @internal
	 */
	protected function compilePivots() {
		
		$query = $this->query;
		$pivots = $query->getPivots();
		
		// no pivoting needed
		if(empty($pivots))
			return;
		
		// set the predicates context and figure out if we need to begin by
		// joining with an AND or not
		($predicates = $query->getPredicates())
		 and ($predicates = $predicates->where())
		 or ($predicates = where());
				
		$join_with_and = !$predicates->contextIsEmpty();
		
		// add in the pivots
		foreach($pivots as $left_alias => $rights) {
			
			$left_name = $query->getUnaliasedModelName($left_alias);
			
			foreach($rights as $right_alias => $pivot_type) {
				
				$right_name = $query->getUnaliasedModelName($right_alias);
				
				// find a path between the two models
				$path = $this->relations->getPath(
					$left_name, 
					$right_name,
					$this->models
				);
								
				// no path, ignore this pivot
				if(empty($path))
					continue;
				
				// join onto the end of the predicates array
				if($join_with_and)
					$predicates->and;
				
				// figure out which side of the path to pivot on. $model_alias
				// is set because we need to make sure we're pivoting on the
				// model *alais* and not the model name.
				if($pivot_type & Query::PIVOT_LEFT) {
					$path = current($path);
					$model_alias = $left_alias;
				} else {
					$path = end($path);
					$model_alias = $right_alias;
				}
				
				// add in the pivot using a keyed substitute
				$predicates->$model_alias($path[1])->eq->_($path[1]);
				
				// every other pivot after this needs to be joined with an AND
				$join_with_and = TRUE;
			}
		}
		
		// make sure that the predicates are set to the query if they weren't
		// already
		$query->setPredicates($predicates);
	}
	
	/**
	 * $c->compile(Query, [int $flags[, array $args]]) -> string
	 *
	 * Compile a certain type of query into a string representation of that
	 * query.
	 */
	public function compile(Query $query, $flags = 0, array &$args = array()) {
		
		$this->query = $query;
		
		$query_types = Query::SELECT | Query::UPDATE | Query::INSERT | Query::DELETE;
		$this->query_type = $flags & $query_types;
		
		switch($flags) {
			case Query::SELECT:
				return $this->compileSelect();
			
			case Query::UPDATE:
				return $this->compileUpdate($args);
			
			case Query::INSERT:
				return $this->compileInsert($args);
			
			case Query::DELETE:
				return $this->compileDelete();
		}
	}
	
	/**
	 * $c->compileOperand(array) -> string
	 *
	 * Compile predicate operand into a string value of itself.
	 *
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
	abstract protected function compileReference($field, $model_alias = NULL);
	abstract protected function compileSubstitute($key = NULL);
	abstract protected function compileLiteral($value);
	abstract protected function compileImmediate($value);
	
	// operators
	abstract protected function compilePrefixOperator($operator, $operand);
	abstract protected function compileInfixOperator($operator, $op1, $op2);
	abstract protected function compileSearchOperator(array $search);
	
	// query types
	abstract protected function compileSelect();
	abstract protected function compileUpdate(array &$args = array());        
	abstract protected function compileInsert(array &$args = array());   
	abstract protected function compileDelete();
}
