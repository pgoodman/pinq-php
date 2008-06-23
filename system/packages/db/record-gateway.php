<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class DatabaseRecordGateway extends RecordGateway {
	
	protected function getRecord(array $data) {
		return QueryDecompiler::getRecord($data, $this->models);
	}
	
	protected function getRecordIterator($result) {
		return new DatabaseRecordIterator($result, $this->ds, $this->models);
	}
	
	/**
	 * Compile a specific type of query given an abstract query and the query
	 * type.
	 */
	protected function compileQuery(Query $query, $type) {
		$compiler = new DatabaseQueryCompiler($query, $this->models, $this->ds);
		return $compiler->compile($type);
	}
	
	/**
	 * Get a specific model gateway.
	 */
	protected function getModelGateway($model_name) {
		$gateway = new DatabaseModelGateway($this->ds, $this->models);
		$gateway->setName($model_name);
		
		return $gateway;
	}
}
