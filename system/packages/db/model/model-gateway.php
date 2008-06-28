<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The model gateway is the link between the model layer and the database.
 * @author Peter Goodman
 */
class DatabaseModelGateway extends ModelGateway {
	
	protected $_compiler;
	
	public function __destruct() {
		parent::__destruct();
		unset($this->_compiler);
	}
	
	/**
	 * Get a single record from the database. This somewhat circuitous of a
	 * route--oh well.
	 */
	protected function getRecord($result_resource) {
		$record_iterator = $this->getRecordIterator($result_resource);
		
		if(0 === count($record_iterator))
			return NULL;
		
		return $record_iterator->current();
	}
	
	/**
	 * Get a record iterator.
	 */
	protected function getRecordIterator($result_resource) {
		return new DatabaseRecordIterator(
			$this->_ds->getRecordIterator($result_resource), 
			$this->_models
		);
	}
	
	/**
	 * Compile a query.
	 */
	protected function compileQuery(Query $query, $type) {
		
		// cache the compiler
		if($this->_compiler === NULL) {
			$this->_compiler = $this->_ds->getQueryCompiler(
				$this->_models,
				$this->_relations
			);
		}
		
		// chaneg the query stored in the compiler
		$this->_compiler->setQuery($query);
		
		return $this->_compiler->compile($type);
	}
}
