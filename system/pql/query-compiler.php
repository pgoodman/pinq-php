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
	          $models;
	
	/**
	 * Constructor, bring in the query and models.
	 */
	public function __construct(Query $query, Dictionary $models) {
		$this->query = $query;
		$this->models = $models;
	}
	
	/**
	 * Destructor, break references.
	 */
	public function __destruct() {
		unset(
			$this->query,
			$this->models
		);
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
				
		// try to get the absolute model name
		$model = $this->getModel($model_alias);
		if(NULL !== ($abs_name = $model->getName()))
			return $abs_name;
		
		// try to get the model name
		if(NULL !== ($model_name = $this->query->getUnaliasedModelName($model_alias)))
			return $model_name;
		
		// oh well, nothing was accomplished :(
		return $model_alias;
	}
	
	/**
	 * Get a model given a model alias.
	 */
	protected function getModel($model_alias) {
		$model_name = $this->query->getUnaliasedModelName($model_alias);
				
		// bad model name
		if(NULL === ($model = $this->models[(string)$model_name])) {
			throw new UnexpectedValueException(
				"Model [{$model_name}] in PQL query does not exist."
			);
		}
		
		return $model;
	}
	
	/**
	 * Return a graph of the dependencies for this query, that is, lay out the
	 * links made in the query such that the links will occur in the proper
	 * order.
	 * @internal
	 */
	protected function getDependencyGraph() {
		
		// the table of relations established through the query. we actually
		// use a copy of the relations because later on it will be modified
		// and added to (for through relations).
		$aliases = &$this->query->getAliases();
		$relations = $this->query->getRelations();
		
		// to quickly access deeper areas in the graph, we will store
		// references to each place where these nodes show up in the graph
		// this is dependable based on the assumption that we've identified
		// all models uniquely through aliasing
		$entry_points = array();
		
		// we want to keep track of the trunks in the graph (think of the 
		// graph as a forest, where each tree is the dependencies for a model)
		$trunks = array();
		
		// temporary indexes for through queries
		$t = 1;
		
		// populate the entry nodes array. this is actually *more* complicated
		// than solving datasource dependencies because we need to sneak the
		// indirect relationships in.
		foreach($relations as $left => $rights) {
			
			$entry_points[$left] = NULL;
			$trunks[$left] = TRUE;
			
			// we need entry points for each alias. for most relations there
			// will only by one model in $rights.
			foreach($rights as $right) {
				
				// we could be doing a deep link, that is, implicity
				// going through other models to get from $left to $right.
				// if that's the case then we will add these dependencies
				// into the graph.
				$path = ModelRelation::findPath(
					$aliases[$left], 
					$aliases[$right], 
					$this->models
				);
				
				// path will have no less than 2 arrays in it
				$count = count($path);
				
				if($count > 1) {
					
					// fix the aliases on the first and last models in the
					// path. this has to be done because the relation finds
					// unambiguous paths using model names, not aliases.
					$path[0][0] = $left;
					$path[$count-1][0] = $right;
					
					// remove the last model from being related to the
					// first.
					$key = array_search($right, $relations[$left]);
					unset($relations[$left][$key]);
					
					// go through the path and add the through relations into
					// the $relations array and give them entry points
					$last = $path[0][0];
					for($i = 1; $i < $count; $i += 2) {
						
						// because some of these are intermediate join tables
						// we don't wan't to assume that thay're not being
						// used elsewhere in the query so we alias them
						$name = $i & 1 && $i < $count-1 ? 't'. $t++
						                                : $path[$i][0];
						
						// add the joining table into the link
						$relations[$last][] = $name;
						$entry_points[$name] = NULL;
						
						if(!isset($aliases[$name]))
							
							// this is listed in the aliases
							if(isset($aliases[$path[$i][0]]))
								$aliases[$name] = $aliases[$path[$i][0]];
							
							// it isn't listed in the aliases, it's likely
							// beensubstituted in by the relations path, take
							// it as is.
							else
								$aliases[$name] = $path[$i][0];
						
						// we need to keep the name (which could be an alias)
						// for the next iteration
						$last = $name;
					}
				}
				
				// set the default value for this entry point
				$entry_points[$right] = NULL;
			}
		}
				
		// go over the relations and build up the dependency graph. the graph
		// is structured as a multi-dimensional associative array. the keys on
		// the first level are the base things that we're trying to get that
		// need their dependencies satisfied. each of these are an associative
		// array, with keys being dependencies. this regresses as far as need
		// be.
		// for example:
		// 
		// 'post' => array(               // post depends on users and content
		//     'users' => array(          // users depends on profiles
		//         'profiles' => array(), // profiles has no dependencies
		//     ),                         
		//     'content' => array(),	  // content has no dependencies
		// )
		//
		// this algorithm works for a very simple reason: all data sources
		// need to be uniquely aliased. that means that every key in the
		// dependency graph will be unique. given this, we build up the graph
		// using a flat array and then trim off items that don't belong in the
		// base level. the way this is done is by using keys as entry points
		// into deep parts of the graph. when we need to make something
		// dependent on another thing, we give it a reference to the entry
		// point, thus extending the graph deeper.
		foreach($relations as $left => $rights) {
			
			// ignore a left with no right relations
			if(empty($rights))
				continue;
			
			foreach($rights as $right) {
				
				// make sure we don't overwrite anything already done
				if(!isset($entry_points[$left][$right]))
					$entry_points[$left][$right] = array();
				
				// this hasn't been used
				if(empty($entry_points[$right]))
					$entry_points[$right] = &$entry_points[$left][$right];
				
				// we're adding an existing tree as a branch onto this item
				else 
					$entry_points[$left][$right] = &$entry_points[$right];
				
				// make sure we don't see the right item as a trunk
				unset($trunks[$right]);
			}
		}
		
		// take out any non-trunk items from the entry points array. these
		// are the final joins.
		return array_intersect_key($entry_points, $trunks);
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
				$path = ModelRelation::findPath(
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
