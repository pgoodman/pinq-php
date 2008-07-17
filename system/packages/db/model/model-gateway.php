<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The model gateway is the link between the model layer and the database.
 * @author Peter Goodman
 */
class PinqDatabaseModelGateway extends RelationalModelGateway {
	
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
		return new PinqDatabaseRecordIterator(
			$this->_data_source->getRecordIterator($result_resource), 
			$this
		);
	}
}
