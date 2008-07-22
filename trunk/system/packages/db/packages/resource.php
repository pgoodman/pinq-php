<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class PinqDbResource extends Resource
                              implements InstantiablePackage {
		
	/**
	 * Close the database connection.
	 */
	public function __del__() {
		$this->disconnect();
	}
	
	/**
	 * $d->GET(string $query[, array $args]) -> RecordIterator
	 */
	public function GET($query) {
		return $this->select($query);
	}
	
	/**
	 * Update rows in the database.
	 */
	public function PUT($query) {
		return (bool)$this->update($query);
	}
	
	/**
	 * Insert rows into the database.
	 */
	public function POST($query) {
		if($this->update($query))
			return $this->getInsertId();
		
		return FALSE;
	}
	
	/**
	 * Delete rows from the database.
	 */
	public function DELETE($query) {
		return (bool)$this->update($query);
	}
	
	/**
	 * $r->getPqlQueryCompiler(Dictionary, PinqModelRelationalManager)
	 * -> QueryCompiler
	 *
	 * Get a PQL query compiler.
	 */
	public function getPqlQueryCompiler(Dictionary $models, 
	                    PinqModelRelationalManager $relations) {
	
		return $this->_packages->load('pql.query-compiler', array(
			$models,
			$relations,
			$this
		));
	}
	
	/**
	 * The bare bones of what's needed to abstract a database.
	 */
	abstract public function connect($host, $user = '', $pass = '', $db = '');
	abstract protected function disconnect();
	
	abstract protected function select($query);
	abstract protected function update($query);
	
	abstract protected function error();
	abstract protected function getInsertId();
	abstract public function quote($str);
}
