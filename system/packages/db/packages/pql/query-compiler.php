<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Given an abstract query and the currently existing models, create a valid
 * SQL query.
 * @author Peter Goodman
 */
class PinqDbPqlQueryCompiler extends QueryCompiler 
                             implements InstantiablePackage {
	
	// needed in query compilation for immediate constants
	protected $db;
	
	/**
	 * Constructor, redefined to bring in the database.
	 */
	public function __construct(Dictionary $models, 
	          PinqModelRelationalRelations $relations,
	                              Resource $db) {
		
		parent::__construct($models, $relations);
		$this->db = $db;
	}
	
	/**
	 * Destructor, obvious.
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->db);
	}
	
	/**
	 * Build up the select fields.
	 * @internal
	 */
	protected function compileFields() {
		
		$query = $this->query;
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
			$model_name = $query->getUnaliasedModelName($model_alias);
			$table_alias = $model_alias;
			
			// if we're selecting all of the columns from this table then we
			// will remove the ALL flag an merge the expanded column names
			// into the query.
						
			if(isset($columns[(string)ALL])) {
				
				// get rid of this, it is no longer needed
				unset($columns[(string)ALL]);
				
				// get the expanded model fields and merge them into the
				// select column, preserving any other custom select columns.
				$temp = $this->models[$model_name]->getFields();
				
				$columns = array_merge(
					array_combine($temp, $temp), 
					$columns
				);
			}
			
			// this might be evil...
			//
			// Assume we are selecting data from two or more database
			// tables at once, some of which have an 'id' column. In a normal
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
			// them somewhat unique. All columns are then prefixed 
			// by the model names which we know from the delimiters to avoid
			// having to find the conflicting items in the first place. Thus,
			// we get EXTRA info from the delimiters: the model name, which
			// we can then use in InnerRecord to allow the programmer to
			// access the information from the joined tables separately.
			$select[] = "1 AS __{$model_name}";
						
			// we're getting multiple columns from this table
			foreach($columns as $alias => $column)
				$select[] = "{$table_alias}.{$column} AS {$model_name}_{$alias}";
			
			// if we're doing any counting the we need to include these columns
			// as well
			foreach($counts as $alias => $column) {
				
				$select[] = "COUNT({$table_alias}.{$column}) AS ".
				            "{$model_name}_{$alias}";
			}
		}
		
		return implode(', ', $select);
	}
	
	/**
	 * Build the SQL required to build up the FROM section of the query. Given
	 * a graph (or sub-graph) of table dependencies. This function builds up
	 * the joins recursively.
	 * @internal
	 */
	protected function recursiveJoin($dependent_model_alias, 
	                           array $graph = array(), 
	                                 $prefix) {		
		$sql = '';
		$comma = '';
		$query = $this->query;
		
		$dependent_model_name = $query->getUnaliasedModelName(
			$dependent_model_alias
		);
		
		foreach($graph as $model_alias => $dependencies) {
			
			// if the alias is the same as the table name then we don't want
			// to alias it
			$model_name = $this->query->getUnaliasedModelName($model_alias);
			$table_name = $this->getInternalModelName($model_name);
			
			if($model_alias == $table_name)
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
				
				$sql .= "({$table_name} {$model_alias} {$joins})";
			
			// no more recursion necessary, start popping off the call stack
			} else
				$sql .= "{$table_name} {$model_alias}";
			
			// if we have something to join on, do it
			
			if(!empty($dependent_model_alias)) {
				
				// this will return direct relations each time. The ordering
				// is arbitrary. Note that most of this has already been
				// cached when we generated the graph so this function is
				// essentially free	
				$path = $this->relations->getPath(
					$dependent_model_name,
					$model_name,
					$this->models
				);
				
				if(empty($path))
					continue;
				
				// get the left and right columns out of the first relation				
				$right_column = $path[0][1];
				$left_column = $path[1][1];
				
				$sql .= " ON {$dependent_model_alias}.{$right_column}=".
				        "{$model_alias}.{$left_column}";
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
		
		$contexts = $this->query->getContexts();
		$use_selects = $this->query_type === self::SELECT;
		
		// only one table is being used and we're not doing a select query,
		// ie: there is no field aliasing
		if(count($contexts) === 1 && !$use_selects)
			return $field;
		
		// this is somewhat of a wild guess that assumes there is only one
		// model being selected from
		if(empty($model_alias)) {
			reset($contexts);
			$model_alias = key($contexts);
		}
		
		// model name
		$model_name = $this->query->getUnaliasedModelName($model_alias);
		
		// this will probably cause a problem in the query, which is a good
		// thing
		if(!isset($contexts[$model_alias]))
			return "{$model_alias}.{$field}";
		
		// if we're dealing with a query-defined alias that's different than
		// the model name
		if($model_alias !== $model_name) {
			
			$context = $contexts[$model_alias];
			
			if($use_selects) {
				
				// special case for COUNT columns
				if(!empty($contexts['select_counts'])) {
					if(isset($context['select_counts'][$field]))
						return "{$model_name}_{$field}";
				}
			
				// special case for aliases SELECT fields
				if(isset($context['select_fields'][$field])) {
					if($context['select_fields'][$field] != $field)
						return "{$model_name}_{$field}";
				}
			}
			
			return "{$model_alias}.{$field}";
		}
		
		// we need to distinguish between models that are being selected from,
		// and therefore their columns will be prefixed, or models that are
		// being used as endpoints in linkin, thus using their model names as
		// aliases.
		//
		// TODO: If the same table is being explicitly joined in the same
		//       query then this could fail. Really it's dependent on $model
		//       being set.
		//
		if($use_selects && !empty($contexts[$model_alias]['select_fields']))
			return "{$model_name}_{$field}";
		
		// note: tables are aliased with their model names, hence the use here
		return "{$model_name}.{$field}";
	}
	
	/**
	 * Compile a substitute value/key.
	 */
	protected function compileSubstitute($key = NULL) {
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
			'eq' => '=', 'is' => '=', 'leq' => '<=', 'geq' => '>=', 
			'lt' => '<', 'gt' => '>', 'neq' => '!=',
			'and' => 'AND', 'or' => 'OR', 'xor' => 'XOR',
		);
		
		// programmer error, die hard!
		if(!isset($infixes[$operator])) {
			throw new UnexpectedValueException(
				"PQL infix operator [{$operator}] is not supported."
			);
		}
		
		return "({$op1} ". $infixes[$operator] ." {$op2})";
	}
	
	/**
	 * Compile the special search operator.
	 * TODO: make this.
	 */
	protected function compileSearchOperator(array $search) {
		
		return '';
	}
	
	/**
	 * Compile a PQL query into a SQL SELECT statement.
	 * @internal
	 */
	protected function compileSelect() {
		
		$pivots = $this->query->getPivots();
		
		// add in what we want to select
		$sql = 'SELECT '. $this->compileFields();

		$aliases = &$this->query->getAliases();
		$relations = &$this->query->getRelations();
		
		// create a graph of the
		$graph = $this->relations->getRelationDependencies(
			$aliases,
			$relations,
			$this->models
		);
		
		if(!empty($graph)) {
			
			// pivots are being used, flatten the graph and translate the
			// nested joins into a flat set of inner joins with the conditions
			// in the WHERE clause
			if(!empty($pivots) && !empty($relations)) {
				$graph = $this->flattenRelationsGraph($graph);
				$this->compileRelationsAsPredicates();
			}
			
			$joins = trim($this->recursiveJoin(NULL, $graph, ''));
			if($joins[0] == '(') {
				$joins = substr($joins, 1, -1);
			}
			$sql .= ' FROM '. $joins;
		}
		
		
		// get parts of the query
		$this->compilePivots();
				
		$where = $this->compilePredicates('where');
		$order = $this->compilePredicates('order');
		$limit = $this->compilePredicates('limit');
		$group = $this->compilePredicates('group');
		
		if(!empty($where)) $sql .= " WHERE ". implode(' ', $where);
		if(!empty($group)) $sql .= " GROUP BY ". implode(' ', $group);
		if(!empty($order)) $sql .= " ORDER BY ". implode(' ', $order);
		if(!empty($limit)) $sql .= " LIMIT ". implode(' ', $limit);
		
		// add in the predicates and return
		return $sql;
	}
	
	/**
	 * Compile a query that works for both UPDATES and DELETES.
	 */
	protected function compileModify($sql, $set = TRUE, array &$args) {
		
		$query = $this->query;
		$contexts = $query->getContexts();
		
		$set_sql = '';
		
		// query isn't cached, build up the sql then cache it.
		$comma = '';
		$set_comma = '';
		
		// go over the tables being modified and construct the beginning of
		// the SQL statement along with the fields to be set
		foreach($contexts as $model_alias => $context) {
			
			$definition = $this->getDefinitionByModelAlias($model_alias);
			$table_name = $definition->getInternalName();
			
			if($table_name === $model_alias)
				$table_name = '';
			
			$sql .= "{$comma}{$table_name} {$model_alias}";
			$comma = ',';
			
			// build up the field listing
			$fields = $this->buildFieldsList(
				$context, 
				$definition, 
				$args
			);
			
			if(count($fields)) {
				$column_prefix = count($contexts) > 1 ? "{$model_alias}." : '';
				foreach($fields as $column => $val) {
					$set_sql .= "{$set_comma} {$column_prefix}{$column}={$val}";
					$set_comma = ',';
				}
			}
		}
		
		// compile the conditions
		$this->compileRelationsAsPredicates();
		$where = $this->compilePredicates('where');
		
		if($this->query_type != self::DELETE && !empty($set_sql))
			$sql .= " SET {$set_sql} ";
		
		if(!empty($where)) $sql .= ' WHERE '. implode(' ', $where);
		
		return $sql;
	}
	
	/**
	 * Build up a fields list.
	 */
	protected function buildFieldsList(array $context, 
	                         PinqModelDefinition $definition, 
	                                  array &$args = array()) {

		$fields = array();
		
		// this step filters out unwanted fields and also replaces substitutes
		// with their values in the $args array
		foreach($context['modify_values'] as $column => $value) {
			
			// done need this
			if(!$definition->hasField($column))
				continue;
			
			// take a value right out of the incoming args
			if($value === _ && !empty($args))
				$value = array_shift($args);
			
			// add this into the fields array for use
			$fields[$column] = $value;
		}
		
		// validate all of the fields. By default this is only an array
		// intersection.
		$fields = $definition->validateFields(
			$fields,
			$this->query_type,
			$context['errors']
		);
		
		// go over the fields and build build up the SQL values for each item.
		foreach($fields as $column => $value) {
			
			if($value !== _) {
				
				// typecast the field's value
				$value = $definition->coerceValueForField(
					$column, 
					$value
				);
			
				// make sure to quote it for insertion as a string
				if(is_string($value))
					$value = "'". $this->db->quote($value) ."'";
				else if(NULL === $value)
					$value = 'NULL';
			
			// substitute value, in general this shouldn't happen
			} else {
				$value = $this->compileSubstitute(NULL);
			}
			
			$fields[$column] = $value;
		}

		return $fields;
	}
	
	/**
	 * Compile an INSERT query. This ignores predicates entirely. Also, this
	 * ignores any relations made in the query and so it will not satisfy
	 * those in any way. This also ingores multiple tables.
	 */
	protected function compileInsert(array &$args = array()) {
		
		// query isn't cached, build up the query
		$queries = array();
		
		// unlike with UPDATE, we can't INSERT into multiple tables at the
		// same time so we will only insert into one table
		$contexts = $this->query->getContexts();
		list($model_alias, $context) = each($contexts);
		
		// get the info for the model
		$definition = $this->getDefinitionByModelAlias($model_alias);
		$table_name = $definition->getInternalName();
		
		$sql = "INSERT INTO {$table_name}";
		$comma = '';
		
		$fields = $this->buildFieldsList($context, $definition, $args);
		
		if(!empty($fields)) {
			$sql .= ' SET';
			$comma = '';
			foreach($fields as $column => $val) {
				$sql .= "{$comma} {$column}={$val}";
				$comma = ',';
			}
		}
			
		return $sql;
	}
	
	/**
	 * Compile an UPDATE query.
	 */
	protected function compileUpdate(array &$args = array()) {
		return $this->compileModify('UPDATE ', TRUE, $args);
	}
	
	/**
	 * Compile a DELETE query.
	 */
	protected function compileDelete() {
		$args = array();
		return $this->compileModify('DELETE FROM ', FALSE, $args);
	}
}
