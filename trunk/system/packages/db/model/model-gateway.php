<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The model gateway is the link between the model layer and the database.
 * @author Peter Goodman
 */
class DatabaseModelGateway extends RelationalModelGateway {
	
	protected $_compiler;
	
	protected function __del__() {
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
		
		$record_iterator->rewind();
		return $record_iterator->current();
	}
	
	/**
	 * Get a record iterator.
	 */
	protected function getRecordIterator($result_resource) {		
		return new DatabaseRecordIterator(
			$this->_ds->getRecordIterator($result_resource), 
			$this
		);
	}
	
	/**
	 * Compile a query.
	 */
	protected function compileQuery(Query $query, $type, array &$args) {
		
		// cache the compiler
		if($this->_compiler === NULL) {
			$this->_compiler = $this->_ds->getQueryCompiler(
				$this->_models,
				$this->_relations
			);
		}
		
		// change the query stored in the compiler
		return $this->_compiler->compile($query, $type, $args);
	}
}
