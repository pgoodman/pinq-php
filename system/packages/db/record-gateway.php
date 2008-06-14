<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseRecordGateway extends RecordGateway {
	
	protected static $types = array(
		ConcreteQuery::SELECT => 'compileSelect',
		ConcreteQuery::INSERT => 'compileInsert',
		ConcreteQuery::UPDATE => 'compileUpdate',
		ConcreteQuery::DELETE => 'compileDelete',
	);
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type.
	 */
	protected function compileQuery(AbstractQuery $query, $type) {
		
		if(!isset(self::$types[$type]))
			return '';
		
		$func = self::$types[$type];
		return DatabaseQuery::$func($query, $this->models);
	}
}
