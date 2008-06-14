<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Given an abstract query and the currently existing models, create a valid
 * SQL query.
 * @author Peter Goodman
 */
class DatabaseQuery extends ConcreteQuery {
	
	/**
	 * Build up the select fields.
	 * @internal
	 */
	static protected function buildSelectFields(AbstractQuery $query,
		                                        Dictionary $models) {
		
		$select = array();
		
		// build up the SELECT columns
		foreach($query->items as $table => $columns) {
						
			// we assume that this exists. if we are being passed the model
			// name then we have to adapt it for SQL as model names are not
			// necessarily the sql table names.
			if($query->aliases[$table] === $table) {
				//$temp = $models[$query->aliases[$table]]->getName();
				//$table = empty($temp) ? $table : $temp;
				$table = self::resolveReference(
					$table, 
					$query->aliases, 
					$models
				);
			}
			
			
			// if we're getting all columns from this table then
			// skip this loop
			if(isset($columns[(string)ALL])) {
				$select[] = "{$table}.*";
				continue;
			}
			
			// we're getting multiple columns from this table
			foreach($columns as $alias => $column) {
				$select[] = "{$table}.{$column} AS {$alias}" ;
			}
		}
		
		// build up the COUNT columns
		foreach($query->counts as $table => $columns) {
			
			// we're getting multiple columns from this table
			foreach($columns as $alias => $column) {
				$select[] = "COUNT({$table}.{$column}) AS {$alias}" ;
			}
		}
		
		return implode(', ', $select);
	}
	
	/**
	 * Build the SQL required to build up the FROM section of the query. Given
	 * a graph (or sub-graph) of table dependencies. This function builds uo
	 * the joins recursively.
	 * @internal
	 */
	static protected function recursiveJoin(array &$aliases = array(), 
		                                    Dictionary &$models, 
		                                    $right, 
									        array &$graph = array(), 
									        $prefix = 'INNER JOIN') {
		$sql = "";
		$comma = "";
		
		foreach($graph as $left => $rights) {
			
			// if the alias is the same as the table name then we don't want
			// to alias it
			$name = self::resolveReference($left, $aliases, $models);
			
			if($name == $left)
				$name = '';
			
			// add in a leading comma for top-level froms and a join prefix
			$sql .= "{$comma} {$prefix} ";
			
			// recursively do the joins
			if(!empty($rights)) {
				
				$joins = self::recursiveJoin(
					$aliases, 
					$models, 
					$left, 
					$rights
				);
				
				$sql .= "({$name} {$left} {$joins})";
			
			// no more recursion necessary, start popping off the call stack
			} else
				$sql .= "{$name} {$left}";
			
			// if we have something to join on, do it
			if(!empty($right)) {
				
				// this will return direct relations each time. The ordering
				// is arbitrary.				
				$relation = AbstractRelation::findPath(
					$aliases[$right],
					$aliases[$left],
					$models
				);
								
				if(empty($relation))
					continue;
				
				// get the left and right columns out of the first relation				
				$right_column = $relation[0][1];
				$left_column = $relation[1][1];
				
				$sql .= " ON {$right}.{$right_column}={$left}.{$left_column}";
			}
			
			// if we are dealing with the top-level froms then they need to
			// be comma-separated
			$comma = empty($prefix) ? ',' : '';
		}
					
		return $sql;
	}
	
	/**
	 * Build up the list of predicates for select/update/delete queries. I must
	 * admit that this is just such an ugly function. I dislike it.
	 * @internal
	 */
	static public function rebuildPredicates(AbstractQuery $query,
		                                     Dictionary $models) {
			
		$predicates = $query->predicates;
		$aliases = $query->aliases;
		$sources = $query->sources;
		
		$sql = '';
		
		foreach($predicates as $predicate) {
		
			list($type, $value) = $predicate;
		
			// operator
			if($type & AbstractPredicates::OP_OPERATOR) {
								
				if($value & AbstractPredicates::OPEN_SUBSET) {
					$sql .= ' (';
					continue;
				} else if($value & AbstractPredicates::CLOSE_SUBSET) {
					$sql .= ')';
					continue;
				}
			
				// arithmatic operators, order matters
				if($value < AbstractPredicates::LOG_IS) {
			
					if($value & AbstractPredicates::LOG_NOT)
						$sql .= ' !';
				
					if($value & AbstractPredicates::LOG_LT)
						$sql .= ' <';
				
					if($value & AbstractPredicates::LOG_GT)
						$sql .= ' >';
				
					if($value & AbstractPredicates::LOG_EQ)
						$sql .= ' =';
			
				// other operators
				} else {
				
					if($value & AbstractPredicates::LOG_AND)
						$sql .= ' AND';
				
					if($value & AbstractPredicates::LOG_OR)
						$sql .= ' OR';
				
					if($value & AbstractPredicates::LOG_IS)
						$sql .= ' IS';
				
					if($value & AbstractPredicates::LOG_NOT)
						$sql .= ' NOT';
				
					if($value & AbstractPredicates::GROUP_BY)
						$sql .= ' GROUP BY';
				
					if($value & AbstractPredicates::ORDER_BY) {
						if($value === AbstractPredicates::ORDER_ASC)
							$sql .= ' ASC';
						else if($value === AbstractPredicates::ORDER_DESC)
							$sql .= ' DESC';
						else
							$sql .= ' ORDER BY';
					}
					
					//if($value & AbstractPredicates::LIKE)
					//	$sql .= ' LIKE';
				
					if($value & AbstractPredicates::LIMIT)
						$sql .= ' LIMIT';
				
					if($value & AbstractPredicates::WHERE)
						$sql .= ' WHERE';
				}
			
			// operand.
			} else {
			
				// special case for substitute values
				if($value === _)
					$sql .= '?';
			
				// column references
				else if($type & AbstractPredicates::VALUE_REFERENCE) {
					
					// resolve the proper table / alias
					$ref = $value[0];
					if(!isset($aliases[$ref]))
						$ref = self::resolveReference($value[0], $aliases, $models);
					
					
					$sql .= " {$ref}.{$value[1]}";
			
				// immediate constants
				} else {
				
					// limit only
					if(is_array($value) && count($value) == 2) {
					
						$sql .= $value[0] === _ ? ' ?' : ' '. (int)$value[0];
						$sql .= $value[1] === _ ? ', ?': ', '. (int)$value[1];
					
					} else
						
						// although we *could* determing the type that this
						// value should follow, I'm not going to (for now)
						$sql .= $value === _ ? ' ?' 
						        : " '". htmlentities($value, ENT_QUOTES) ."'";
				}
			}
		}
		
		return $sql;
	}
	
	/**
	 * Figure out if we can take an SQL table name.
	 * @internal
	 */
	static protected function resolveReference($ref, array $aliases, 
		                                       Dictionary $models) {
		
		// we have been given a model alias which is not the model name		
		if(isset($aliases[$ref]) && ($a = $aliases[$ref]) != $ref) {
			$name = $models[$a]->getName();
			$else = $a;
		
		// we've been given a model name, try to gets its table name
		} else if(isset($models[$ref])) {
			$name = $models[$ref]->getName();
			$else = $ref;
		}
		
		return empty($name) ? $else : $name;
	}
	
	/**
	 * Compile and abstract query into a SQL SELECT statement.
	 */
	static public function compileSelect(AbstractQuery $query, 
		                                 Dictionary $models) {
		
		// we've cached this query, nice.
		if(NULL !== ($cached = self::getCachedQuery($query)))
			return $cached;
		
		// add in what we want to select
		$sql = 'SELECT '. self::buildSelectFields($query, $models);
		
		// add in the tables to get data from and build up the join statements
		// (if any)
		$graph = self::getDependencyGraph($query, $models);
		if(!empty($graph)) {
			$sql .= ' FROM '. self::recursiveJoin(
				$query->aliases,
				$models,
				NULL, 
				$graph, 
				''
			);
		}
		
		// add in the predictes
		$sql .= self::rebuildPredicates($query, $models);
		
		// cache the query
		self::cacheQuery($query, $sql);
		
		// add in the predicates and return
		return $sql;
	}
	
	/**
	 * Compile a query that works for both UPDATES and DELETES.
	 */
	static protected function compileModify(AbstractQuery $query, 
		                                    Dictionary $models, $prefix,
		                                    $set = TRUE) {
		
		// we've cached this query, nice.
		if(NULL !== ($cached = self::getCachedQuery($query)))
			return $cached;
		
		// query isn't cached, build up the sql then cache it.
		$sql = "{$prefix} ";
		$comma = '';
		
		foreach($query->sources as $alias => $name) {
			
			if($alias === $name)
				$alias = '';
				
			$sql .= "{$comma}{$name} {$alias}";
			$comma = ',';
		}
		
		if($set) {
			$sql .= ' SET';
		
			$comma = '';
			foreach($query->values as $alias => $fields) {
			
				foreach($fields as $column => $value) {
					$sql .= "{$comma} {$alias}.{$column}=". $models[$alias]->castValue(
						$column, 
						$value
					);
					$comma = ',';
				}
			}
		}
		
		// note it's not by reference!! why? well if we use the same query
		// for multiple things (inser/update/delete) and pass it around, then
		// each time it will grow the predicates and a useless way, so we
		// want to work with a copy of the predicates array (which complicates
		// some things)
		$predicates = $query->predicates;
				
		// find what value we're pivoting on. unfortunately this doesn't do
		// extensive checks on the predicates to make sure such things ought
		// be pivots, but whatever.
		$pivots = array();
		foreach($predicates as $predicate) {
			list($type, $value) = $predicate;
			
			if($type & AbstractPredicates::VALUE_REFERENCE)
				$pivots[$value[0]] = $value[1];
		}
		
		$aliases = &$query->aliases;
		$has_predicates = !empty($predicates);
		
		// if we're working with predicates, then we want to isolate them off
		// from any other predicates we might add by doing relations
		if($has_predicates) {
			$where = array_shift($predicates);
			array_unshift($predicates, $where, array(
				AbstractPredicates::OP_OPERATOR, 
				AbstractPredicates::OPEN_SUBSET,
			));
			$predicates[] = array(
				AbstractPredicates::OP_OPERATOR, 
				AbstractPredicates::CLOSE_SUBSET
			);
		}
		
		// add in the relationships as predicates
		foreach($query->relations as $left => $rights) {
			foreach($rights as $right) {
				
				// find a path
				$path = AbstractRelation::findPath(
					$aliases[$right],
					$aliases[$left],
					$models
				);
				
				// make sure we're not using an indirect relationship
				if(count($path) > 2) {
					throw new DomainException(
						"Models related in an update/insert query must be ".
						"directly related."
					);
				}
				
				// adjust for aliasing
				$path[0][0] = $aliases[$path[0][0]];
				$path[1][0] = $aliases[$path[1][0]];
				
				// join this condition into the predicates
				if($has_predicates) {
					$predicates[] = array(
						AbstractPredicates::OP_OPERATOR, 
						AbstractPredicates::LOG_AND,
					);
				}
				
				// add in the condition to join these tables in the update
				$predicates[] = array(
					AbstractPredicates::OP_OPERAND | AbstractPredicates::VALUE_REFERENCE,
					$path[0],
				);
				$predicates[] = array(
					AbstractPredicates::OP_OPERATOR, 
					AbstractPredicates::LOG_EQ,
				);
				$predicates[] = array(
					AbstractPredicates::OP_OPERAND | AbstractPredicates::VALUE_REFERENCE,
					$path[1],
				);
			}
		}
				
		// add in the predicates, note that we use empty() here instead of
		// $has_predicates.
		if(!empty($predicates))
			$sql .= self::rebuildPredicates($query, $models);
		
		// cache the query
		self::cacheQuery($query, $sql);
		
		// add in the predicates and return
		return $sql;
	}
	
	/**
	 * Compile an INSERT query. This ignores predicates entirely. Also, this
	 * ignores any relations made in the query and so it will not satisfy
	 * those in any way.
	 */
	static public function compileInsert(AbstractQuery $query, Dictionary $models) {
		
		// we've cached this query, nice.
		if(NULL !== ($cached = self::getCachedQuery($query)))
			return $cached;
		
		// query isn't cached, build up the query
		$queries = array();
		$values = &$query->values;
		
		// unlike with UPDATE, we can't INSERT into multiple tables at the
		// same time, but it doesn't mean that we can't chain several insert
		// queries together.
		foreach($query->sources as $alias => $name) {
			
			$sql = "INSERT INTO {$name}";
			$comma = '';
			
			// one way to do an insert is to follow it with a select. this
			// system doesn't directly support that, but someone could
			// concatenate two compiled queries to get the same effect. To
			// allow for this, we make sure that we only insert SET if we
			// need to.
			if(!empty($values[$alias]))
				$sql .= ' SET';
			
			// go over the insert values
			foreach($values[$alias] as $column => $value) {
				$sql .= "{$comma} {$column}=". $models[$alias]->castValue(
					$column, 
					$value
				);
				$comma = ',';
			}
			
			// add this insert query to the queries
			$queries[] = $sql;
		}
		
		// we only have one query so return it instead of the array
		if(count($queries) == 1)
			$queries = $queries[0];
		
		// cache the query
		self::cacheQuery($query, $queries);
		
		return $queries;
	}
	
	/**
	 * Compile an UPDATE query.
	 */
	static public function compileUpdate(AbstractQuery $query, Dictionary $models) {
		return self::compileModify(
			$query,
			$models,
			'UPDATE',
			TRUE
		);
	}
	
	/**
	 * Compile a DELETE query.
	 */
	static public function compileDelete(AbstractQuery $query, Dictionary $models) {
		return self::compileModify(
			$query,
			$models,
			'DELETE FROM',
			FALSE
		);
	}
}
