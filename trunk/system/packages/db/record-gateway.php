<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseRecordGateway extends RecordGateway {
	
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
	
	/**
	 * Get a specific model gateway.
	 */
	protected function getModelGateway() {
		return new DatabaseModelGateway($this->ds, $this->models);
	}
}
