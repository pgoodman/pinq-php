<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseModelGateway extends ModelGateway {
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type. Ugh, same as DatabaseRecordGateway::compileQuery.
	 */
	protected function compileQuery(Query $query, $type) {
		$compiler = new DatabaseQueryCompiler($query, $this->models, $this->ds);
		return $compiler->compile($type);
	}
	
	/**
	 * Get a new instance of this class.
	 */
	public function getModelGateway() {
		return new self($this->ds, $this->models);
	}
}
