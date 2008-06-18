<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for a record.
 */
interface Record extends ArrayAccess, Object {
	public function isSaved();
	public function isDeleted();
	public function save();
	public function delete();
}

/**
 * A data record, this implements the generic things every record needs.
 */
abstract class AbstractRecord extends Dictionary implements Record {
	
	protected $_is_saved = FALSE,
	          $_is_deleted = FALSE,
	          $_name,
	          $_sub_records;
	
	/**
	 * Is this record saved to a data source?
	 */
	public function isSaved() {
		return $this->_is_saved;
	}
	
	/**
	 * Is this record deleted? (but just lingering until it is garbage-
	 * collected).
	 */
	public function isDeleted() {
		return $this->_is_deleted;
	}
	
	/**
	 * Save this record to the data source.
	 */
	public function save() {
		$this->_is_saved = TRUE;
	}
	
	/**
	 * Delete this record from a data source.
	 */
	public function delete() {
		if($this->isSaved())
			$this->_is_deleted = TRUE;
	}
	
	/**
	 * Set the model name of this record.
	 */
	public function setName($name) {
		$this->_name = $name;
	}
	
	/**
	 * Get the model name of this record.
	 */
	public function getName() {
		return $this->_name;
	}
	
	/**
	 * Is this a named record, or possibly a mix of many sub-records OR was it
	 * a record found through straight SQL?
	 */
	public function isNamed() {
		return NULL !== $this->_name;
	}
	
	/**
	 * Set the sub-records for this record, thus making this record
	 * ambiguous.
	 */
	protected function setSubRecords(array &$records) {
		
		if(count($records) < 2)
			return;
		
		$this->_name = NULL;
		$this->_sub_records = &$records;
	}
	
	/**
	 * Dig into the result tables selected.
	 */
	public function __get($model_name) {
		
		// we might be trying to build a related query, if many records exist
		// within this record then we cannot do it as this record is
		// considered ambiguous.
		if(!isset($this->_sub_records[$model_name]))
			return NULL;
		
		// return the sub-database record
		return $this->_sub_records[$model_name];
	}
}

/**
 * A record holding a record. This allows stacking of records.
 */
abstract class OuterRecord implements Record {
	
	// and instance of a Record class
	protected $record;
	
	/**
	 * Bring in the record to hold.
	 */
	public function __construct(Record $record) {
		$this->record = $record;
	}
	
	/**
	 * Get the record that this outer record holds.
	 */
	public function getInnerRecord() {
		return $this->record;
	}
	
	/**
	 * Record methods.
	 */
	public function offsetGet($key) { 
		return $this->record->offsetGet($key); 
	}
	public function offsetSet($key, $val) { 
		return $this->record->offsetSet($key, $val); 
	}
	public function offsetExists($key) { 
		return $this->record->offsetExists($key); 
	}
	public function offsetUnset($key) {
		return $this->record->offsetUnset($key);
	}
}
