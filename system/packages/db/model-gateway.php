<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseModelGateway extends ModelGateway {
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type. Ugh, same as DatabaseRecordGateway::compileQuery.
	 */
	protected function compileQuery(AbstractQuery $query, $type) {
		
		if(!isset(self::$types[$type]))
			return '';
		
		$func = self::$types[$type];
		return DatabaseQuery::$func($query, $this->models);
	}
	
	/**
	 * Get a new instance of this class.
	 */
	public function getModelGateway() {
		return new self($this->ds, $this->models);
	}
}
