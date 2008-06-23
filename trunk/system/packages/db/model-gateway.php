<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Ugh. This class is the same as the database record gateway. I'm disappointed
 * in myself.
 * @author Peter Goodman
 */
class DatabaseModelGateway extends ModelGateway {
	
	protected function getRecord(array $data) {
		return QueryDecompiler::getRecord($data, $this->models);
	}
	
	protected function getRecordIterator($result) {
		return new DatabaseRecordIterator($result, $this->ds, $this->models);
	}
	
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
	public function getModelGateway($model_name) {
		$gateway = new self($this->ds, $this->models);
		$gateway->setName($model_name);
		
		return $gateway;
	}
}
