<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseRecordGateway extends RecordGateway {
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type.
	 */
	protected function compileQuery(AbstractQuery $query, $type) {
		$compiler = new DatabaseQuery($query, $this->models);
		return $compiler->compileByType($type);
	}
	
	/**
	 * Get a specific model gateway.
	 */
	protected function getModelGateway() {
		return new DatabaseModelGateway($this->ds, $this->models);
	}
}
