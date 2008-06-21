<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Given an abstract query and the currently existing models, create a valid
 * SQL query.
 * @author Peter Goodman
 */
class DatabaseQueryCompiler extends QueryCompiler {
	
	// needed in query compilation for immediate constants
	protected $db;
	
	/**
	 * Constructor, redefined to bring in the database.
	 */
	public function __construct(Query $query, Dictionary $models, Database $db) {
		parent::__construct($query, $models);
		$this->db = $db;
	}
	
	/**
	 * Build up the select fields.
	 * @internal
	 */
	protected function compileFields() {
		
		$query = &$this->query;
		$select = array();
		
		// a signal that the PQL is being used as opposed to traditional SQL
		$select[] = "1 AS __pql__";

		// build up the SELECT columns, including any columns being COUNTed.
		foreach($query->getContexts() as $model_alias => $context) {
			
			$counts = $context['select_counts'];
			$columns = $context['select_fields'];
			
			// skip this, we don't care
			if(empty($columns) && empty($counts))
				continue;
			
			// we assume that this exists. if we are being passed the model
			// name then we have to adapt it for SQL as model names are not
			// necessarily the sql table names.
			$model_name = $query->getModelName($model_alias);
			$table_alias = $model_alias;
			//$table_name = $this->getModelStructName($model_name);
			
			// if we're selecting all of the columns from this table then we
			// will remove the ALL flag an merge the expanded column names
			// into the query.
						
			if(isset($columns[(string)ALL])) {
				
				// get rid of this, it is no longer needed
				unset($columns[(string)ALL]);
				
				// get the expanded model fields and merge them into the
				// select column, preserving any other custom select columns.
				$temp = array_keys($this->models[$model_name]->_properties);
				$columns = array_merge(array_combine($temp, $temp), $columns);
			}
			
			// this might be evil
			// Why am I doing this? Assume we are selecting data from two
			// tables at once, both of which have an 'id' column. In a normal
			// select, the value for one would overwrite the value for another
			// and so we would actually LOSE some possibly useful data. The
			// solution is three-fold. We need to
			// 1) identify possible conflicting columns
			// 2) identify where select fields for each table start, thus we
			//    need to delimit them somehow.
			// 3) prefix the conflicting columns with something so that we
			//    can identify and remove the prefixes later.
			//
			// The solution is simple: the table delimiters (as follows in
			// code) have the model name in them, prefixed by '__' to make
			// them somewhat unique. Conflicting columns are then prefixed
			// by the model names which we know from the delimiters. Thus,
			// we get EXTRA info from the delimiters: the model name, which
			// we can then use in DatabaseRecord to allow the programmer to
			// access the information from the joined tables separately.
			$select[] = "1 AS __{$model_name}";
						
			// we're getting multiple columns from this table
			foreach($columns as $alias => $column)
				$select[] = "{$table_alias}.{$column} AS {$model_name}_{$alias}";
			
			// if we're doing any counting the we need to include these columns
			// as well
			foreach($counts as $allias => $column) {
				$select[] = "COUNT({$table_alias}.{$column}) AS ".
				            "{$model_name}_{$alias}";
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
	protected function recursiveJoin($dependent_model_name, 
		                             array &$graph = array(), 
		                             $prefix) {
		
		$sql = "";
		$comma = "";
		
		$models = $this->models;
		$query = $this->query;
		
		foreach($graph as $model_name => $dependencies) {
			
			// if the alias is the same as the table name then we don't want
			// to alias it
			if($model_name == ($table_name = $this->getModelStructName($model_name)))
				$table_name = '';
			
			// add in a leading comma for top-level froms and a join prefix
			$sql .= "{$comma} {$prefix} ";
			
			// recursively do the joins
			if(!empty($dependencies)) {
				
				$joins = $this->recursiveJoin(
					$model_name, 
					$dependencies,
					'INNER JOIN'
				);
				
				$sql .= "({$table_name} {$model_name} {$joins})";
			
			// no more recursion necessary, start popping off the call stack
			} else
				$sql .= "{$table_name} {$model_name}";
			
			// if we have something to join on, do it
			if(!empty($dependent_model_name)) {
				
				// this will return direct relations each time. The ordering
				// is arbitrary.				
				$relation = ModelRelation::findPath(
					$query->getModelName($dependent_model_name),
					$query->getModelName($model_name),
					$models
				);
								
				if(empty($relation))
					continue;
				
				// get the left and right columns out of the first relation				
				$right_column = $relation[0][1];
				$left_column = $relation[1][1];
				
				$sql .= " ON {$dependent_model_name}.{$right_column}=".
				        "{$model_name}.{$left_column}";
			}
			
			// if we are dealing with the top-level froms then they need to
			// be comma-separated
			$comma = empty($prefix) ? ',' : '';
		}
					
		return $sql;
	}
	
	/**
	 * Compile a reference to a column in a database table.
	 */
	protected function compileReference($field, $model_alias = NULL) {
		
		// this is somewhat of a wild guess that assumes there is only one
		// model being selected from
		if($model_alias === NULL)
			$model_alias = key($this->query->getContexts());
		
		$model_name = $this->query->getModelName($model_alias);
		
		// we need to distinguish between models that are being selected from,
		// and therefore their columns will be prefixed, or models that are
		// being used as endpoints in linkin, thus using their model names as
		// aliases.
		//
		// TODO: If the same table is being explicitly joined in the same
		//       query then this could fail. Really it's dependent on $model
		//       being set.
		$contexts = $this->query->getContexts();
		if(isset($contexts[$model_alias]))
			if(!empty($contexts[$model_alias]['select_fields']))
				return "{$model_name}_{$field}";
		
		// note: tables are aliased with their model names, hence the use here
		return "{$model_name}.{$field}";
	}
	
	/**
	 * Compile a substitute value/key.
	 */
	protected function compileSubstitute($key) {
		if($key === NULL)
			return '?';
		
		return ":{$key}";
	}
	
	/**
	 * Compile a general operand.
	 */
	protected function compileLiteral($value) {
		$upper = strtoupper($value);
		$literals = array('ASC', 'DESC', 'HAVING', 'OFFSET', 'WITH', 'ROLLUP');
		
		if(in_array($upper, $literals))
			return $upper;
		
		return $this->compileImmediate($value);
	}
	
	/**
	 * Compile an immediate constant.
	 */
	protected function compileImmediate($value) {
		
		// integer
		if(is_int($value) || ctype_digit($value))
			return (string)(int)$value;
		
		// floating point
		else if(is_float($value))
			return (string)(float)$value;
		
		// boolean
		else if(is_bool($value))
			return (string)(int)$value;
		
		// null
		else if(NULL === $value || strtoupper($value) == 'NULL')
			return 'NULL';
			
		// string
		else
			return "'". $this->db->quote($value) ."'";
	}
	
	/**
	 * Compile a prefix operator and its operand.
	 */
	protected function compilePrefixOperator($operator, $operand) {
		return strtoupper($operator) ." {$operand}";
	}
	
	/**
	 * Compile an infix operator with its operands.
	 */
	protected function compileInfixOperator($operator, $op1, $op2) {
		$infixes = array(
			'mul' => '*', 'div' => '/',
			'add' => '+', 'sub' => '-',
			'eq' => '=', 'leq' => '<=', 'geq' => '>=', 
			'lt' => '<', 'gt' => '>', 'neq' => '!=',
			'and' => 'AND', 'or' => 'OR', 'xor' => 'XOR',
		);
		
		return "({$op1} ". $infixes[$operator] ." {$op2})";
	}
	
	/**
	 * Compile the special search operator.
	 */
	protected function compileSearchOperator(array $search) {
		
		return '';
	}
	
	/**
	 * Rebuild pivots. Pivots are when we link two tables together in a query
	 * and then want to add an extra predicate so that we can specify a value
	 * to "pivot" the join tables on. Pivots always go with columns from the
	 * left alias.
	 *
	 * Note: this function is called within rebuildPredicates() as it usually
	 *       modifies the predicates array significantly.
	 * 
	 * @internal
	 */
	protected function createPivots() {
		
		$query = $this->query;
		$predicates = $query->getPredicates();
		$pivots = $query->getPivots();
				
		// no pivoting needed
		if(empty($pivots))
			return;
		
		// set the predicates context and figure out if we need to begin by
		// joining with an AND or not
		$predicates
		 and ($predicates = $predicates->where())
		 or ($predicates = where());
				
		$join_with_and = !$predicates->contextIsEmpty();
		
		// add in the pivots
		foreach($pivots as $left_alias => $rights) {
			
			foreach($rights as $right_alias => $pivot_type) {
				
				// find a path between the two models
				$path = ModelRelation::findPath(
					$query->getModelName($left_alias), 
					$query->getModelName($right_alias),
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
	 * Compile a PQL query into a SQL SELECT statement.
	 * @internal
	 */
	protected function compileSelect() {
		
		$time_start = list($sm, $ss) = explode(' ', microtime());
		
		// add in what we want to select
		$sql = 'SELECT '. $this->compileFields();
		
		// add in the tables to get data from and build up the join statements
		// (if any)
		$graph = $this->getDependencyGraph();
		
		if(!empty($graph))
			$sql .= ' FROM '. $this->recursiveJoin(NULL, $graph, '');
		
		// get parts of the query
		$this->createPivots();
		$where = $this->compilePredicates('where');
		$order = $this->compilePredicates('order');
		$limit = $this->compilePredicates('limit');
		$group = $this->compilePredicates('group');
		
		if(!empty($where)) $sql .= " WHERE ". implode(' ', $where);
		if(!empty($group)) $sql .= " GROUP BY ". implode(' ', $group);
		if(!empty($order)) $sql .= " ORDER BY ". implode(' ', $order);
		if(!empty($limit)) $sql .= " LIMIT ". implode(' ', $limit);
		
		out('<pre>', $sql, '</pre>');
		$time_end = list($em, $es) = explode(' ', microtime());
		out('<pre>', 'Query compile time:', ($em + $es) - ($sm + $ss), '</pre>');
		
		// add in the predicates and return
		return $sql;
	}
	
	/**
	 * Compile a query that works for both UPDATES and DELETES.
	 */
	protected function compileModify($prefix, $set = TRUE) {
		
		$query = &$this->query;
		$models = &$this->models;
		
		// query isn't cached, build up the sql then cache it.
		$sql = "{$prefix} ";
		$set = '';
		$comma = '';
		
		foreach($query->getContexts() as $model_alias => $context) {
			
			if($model_alias === $context['name'])
				$model_alias = '';
			
			$sql .= "{$comma}{$context[name]} {$model_alias}";
			$comma = ',';
		}
		/*
		if($set) {
			$sql .= ' SET';
		
			$comma = '';
			foreach($query->_values as $alias => $fields) {
			
				foreach($fields as $column => $value) {
					
					$sql .= "{$comma} {$alias}.{$column}=";
					$sql .= $models[$alias]->castValue($column, $value);
					$comma = ',';
				}
			}
		}*/
		/*
		// note it's not by reference!! why? well if we use the same query
		// for multiple things (inser/update/delete) and pass it around, then
		// each time it will grow the predicates and a useless way.
		$predicates = $query->getPredicates();
				
		// find what value we're pivoting on. unfortunately this doesn't do
		// extensive checks on the predicates to make sure such things ought
		// be pivots, but whatever.
		$pivots = array();
		foreach($predicates as $predicate) {
			
			list($type, $value) = $predicate;
			
			if($type & QueryPredicates::VALUE_REFERENCE)
				$pivots[$value[0]] = $value[1];
		}
		
		$aliases = &$query->getAliases();
		
		$predicates->where();
		$join_with_and = !$predicates->contextIsEmpty();
		
		// isolate the original predicates
		//$this->isolatePredicates();
		
		// add in the relationships as predicates
		foreach($query->getRelations() as $left => $rights) {
			foreach($rights as $right) {
				
				// find a path
				$path = ModelRelation::findPath(
					$aliases[$right],
					$aliases[$left],
					$models
				);
				
				// make sure we're not using an indirect relationship
				if(empty($path) || count($path) > 2) {
					throw new DomainException(
						"Models related in an update/insert query must be ".
						"directly related."
					);
				}
				
				// adjust for aliasing
				$path[0][0] = $aliases[$path[0][0]];
				$path[1][0] = $aliases[$path[1][0]];
				
				// join this condition into the predicates
				if($join_with_and) {
					$predicates[] = array(
						QueryPredicates::OP_OPERATOR, 
						QueryPredicates::LOG_AND,
					);
				}
				
				// add in the condition to join these tables in the update
				$predicates[] = array(
					QueryPredicates::OP_OPERAND | 
					QueryPredicates::VALUE_REFERENCE,
					$path[0],
				);
				$predicates[] = array(
					QueryPredicates::OP_OPERATOR, 
					QueryPredicates::LOG_EQ,
				);
				$predicates[] = array(
					QueryPredicates::OP_OPERAND | 
					QueryPredicates::VALUE_REFERENCE,
					$path[1],
				);
			}
		}
		
		// add in the predicates
		$sql .= $this->rebuildPredicates();
		
		// add in the predicates and return
		return $sql;
		*/
	}
	
	/**
	 * Compile an INSERT query. This ignores predicates entirely. Also, this
	 * ignores any relations made in the query and so it will not satisfy
	 * those in any way.
	 */
	protected function compileInsert() {
		
		// query isn't cached, build up the query
		$queries = array();
		$models = &$this->models;
		
		// unlike with UPDATE, we can't INSERT into multiple tables at the
		// same time, but it doesn't mean that we can't chain several insert
		// queries together.
		foreach($this->query->getContexts() as $model_alias => $context) {
			
			$model = $this->models[$context['name']];
			
			// make sure to get the proper name
			if(NULL === ($name = $model->getName()))
				$name = $context['name'];
			
			$sql = "INSERT INTO {$name}";
			$comma = '';
			
			// one way to do an insert is to follow it with a select. this
			// system doesn't directly support that, but someone could
			// concatenate two compiled queries to get the same effect. To
			// allow for this, we make sure that we only insert SET if we
			// need to.
			if(!empty($context['modfiy_values']))
				$sql .= ' SET';
			
			// go over the insert values
			foreach($context['modfiy_values'] as $column => $value) {
				
				// this is special for databases
				if($value === _)
					$val = '?';
				
				// cast to a specific data type
				else {
					$val = $models[$alias]->coerceValue(
						$column, 
						$value
					);
					
					// make sure to quote it for insertion as a string
					if(is_string($val))
						$val = "'". $this->db->quote($val) ."'";
				}
				
				$sql .= "{$comma} {$column}={$val}";
				$comma = ',';
			}
			
			// add this insert query to the queries
			$queries[] = $sql;
		}
		
		// we only have one query so return it instead of the array
		if(count($queries) == 1)
			$queries = $queries[0];
		
		return $queries;
	}
	
	/**
	 * Compile an UPDATE query.
	 */
	protected function compileUpdate() {
		return $this->compileModify('UPDATE', TRUE);
	}
	
	/**
	 * Compile a DELETE query.
	 */
	protected function compileDelete() {
		return $this->compileModify('DELETE FROM', FALSE);
	}
}
