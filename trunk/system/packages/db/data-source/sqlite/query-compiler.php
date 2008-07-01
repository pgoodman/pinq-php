<?php

class SqliteQueryCompiler extends DatabaseQueryCompiler {
	
	/**
	 * Get the fields separate from their values.
	 */
	protected function buildFieldNameList(array $context, 
		                                  ModelDefinition $definition) {
		
		$fields = array();
		
		foreach($context['modify_values'] as $column => $value) {
			if(!$definition->hasField($column))
				continue;
			
			$fields[] = $column;
		}
		
		return $fields;
	}
	
	/**
	 * Get the values separate from their fields.
	 * @internal
	 */
	protected function buildFieldValueList(array $context, 
		                                    ModelDefinition $definition) {
		
		$fields = array();
		
		// validate all of the fields. this is more of a step where the
		// programmer can deal with any business logic stuff without worrying
		// about manually typecasting the different fields, as that is already
		// done in the loop
		$values = $definition->validateFields(
			$context['modify_values'],
			$this->query_type
		);
		
		foreach($values as $column => $value) {
			
			// ignore non-existant properties
			if(!$definition->hasField($column))
				continue;
			
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
			
			$fields[] = $value;
		}
		
		return $fields;
	}
	
	/**
	 * Compile an INSERT query. SQLite doesn't allow us to use SET syntax for
	 * inserts, which is the default behavior, so we will overwrite that.
	 */
	protected function compileInsert() {
		
		// query isn't cached, build up the query
		$queries = array();
		
		// with SQLite we will only allow inserting into one table
		$contexts = $this->query->getContexts();
		list($model_alias, $context) = each($contexts);
		
		// get the usual stuff
		$definition = $this->getDefinitionByModelAlias($model_alias);
		$table_name = $definition->getInternalName();
		
		// get the field and values
		$fields = $this->buildFieldNameList($context, $definition);
		$values = $this->buildFieldValueList($context, $definition);
		
		// create the query
		$sql = "INSERT INTO {$table_name} ";
		$sql .= '('. implode(',', $fields) .') ';
		$sql .= 'VALUES ('. implode(',', $values) .')';
		
		return $sql;
	}
}
