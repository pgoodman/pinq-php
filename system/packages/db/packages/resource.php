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
	 * $r->GET(string $query) -> RecordIterator
	 */
	public function GET($query) {
		$result = $this->select($query);
		return $this->_packages->loadNew('record-iterator',	array($result));
	}
	
	/**
	 * $r->PUT(string $query) -> {int, bool(FALSE)}
	 *
	 * Insert data into the database.
	 */
	public function PUT($query) {
		if($this->update($query))
			return $this->getInsertId();
		
		return FALSE;
	}
	
	/**
	 * $r->POST(string $query) -> bool
	 *
	 * Update data in the database.
	 */
	public function POST($query) {
		return (bool)$this->update($query);
	}
	
	/**
	 * $r->DELETE(string $query) -> bool
	 *
	 * Delete rows from the database.
	 */
	public function DELETE($query) {
		return (bool)$this->update($query);
	}
	
	/**
	 * $r->getPqlQueryCompiler(Dictionary, PinqModelRelationalMap)
	 * -> PinqPqlQueryCompiler
	 *
	 * Get a PQL query compiler.
	 */
	public function getPqlQueryCompiler(Dictionary $models, 
	                    PinqModelRelationalMap $relations) {
	
		return $this->_packages->load('pql.query-compiler', array(
			$models,
			$relations,
			$this
		));
	}
	
	/**
	 * $r->queryError(string $query, string $error) ! PinqDbException
	 */
	protected function queryError($query, $error) {
		
		$last_error = $this->error();

		if(0 != strcmp($last_error, $error))
			$error .= "\n{$last_error}";
		
		// usually the result of a malformed query. This won't reveal too much
		// assuming proper use of the $args array because the query has not
		// had its substitutes replaced
		throw new PinqDbException(
			"The following database query failed:\n".
			"<pre>{$query}</pre>\n".
			"The error reported was:\n".
			"<pre>{$error}</pre>"
		);
	}
	
	abstract public function connect($host, $user = '', $pass = '', $db = '');
	abstract protected function disconnect();
	
	abstract protected function select($query);
	abstract protected function update($query);
	
	abstract protected function error();
	abstract protected function getInsertId();
	abstract public function quote($str);
}
