<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseRecordGateway extends RecordGateway {
	
	protected function getRecordIterator($result) {
		return new DatabaseRecordIterator($result, $this->ds);
	}
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type.
	 */
	protected function compileQuery(Query $query, $type) {
		$compiler = new DatabaseQueryCompiler($query, $this->models, $this->ds);
		return $compiler->compile($type);
	}
}
