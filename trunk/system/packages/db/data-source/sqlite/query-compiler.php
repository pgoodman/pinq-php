<?php

class SqliteQueryCompiler extends DatabaseQueryCompiler {
	
	/**
	 * Compile an INSERT query. SQLite doesn't allow us to use SET syntax for
	 * inserts, which is the default behavior, so we will overwrite that.
	 */
	protected function compileInsert(array &$args = array()) {
		
		// query isn't cached, build up the query
		$queries = array();
		
		// with SQLite we will only allow inserting into one table
		$contexts = $this->query->getContexts();
		list($model_alias, $context) = each($contexts);
		
		// get the usual stuff
		$definition = $this->getDefinitionByModelAlias($model_alias);
		$table_name = $definition->getInternalName();
		
		// get the field and values
		$fields = $this->buildFieldsList($context, $definition, $args);
		//$values = $this->buildFieldValueList($context, $definition);
		
		// create the query
		$sql = "INSERT INTO {$table_name} ";
		$sql .= '('. implode(',', array_keys($fields)) .') ';
		$sql .= 'VALUES ('. implode(',', array_values($fields)) .')';
		
		return $sql;
	}
}
