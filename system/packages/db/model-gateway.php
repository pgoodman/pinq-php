<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseModelGateway extends ModelGateway {
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type. Ugh, same as DatabaseRecordGateway::compileQuery.
	 */
	protected function compileQuery(AbstractQuery $query, $type) {
		$compiler = new DatabaseQuery($query, $this->models);
		return $compiler->compileByType($type);
	}
	
	/**
	 * Get a new instance of this class.
	 */
	public function getModelGateway() {
		return new self($this->ds, $this->models);
	}
}
