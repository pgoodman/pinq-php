<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for a record.
 */
interface Record extends ArrayAccess {
	public function isSaved();
	public function isDeleted();
	public function save();
	public function delete();
}

/**
 * A data record, this implements the generic things every record needs.
 */
abstract class AbstractRecord extends Dictionary implements Record {
	
	protected $is_saved = FALSE,
	          $is_deleted = FALSE;
	
	/**
	 * Is this record saved to a data source?
	 */
	public function isSaved() {
		return $this->is_saved;
	}
	
	/**
	 * Is this record deleted? (but just lingering until it is garbage-
	 * collected).
	 */
	public function isDeleted() {
		return $this->is_deleted;
	}
	
	/**
	 * Save this record to the data source.
	 */
	public function save() {
		$this->is_saved = TRUE;
	}
	
	/**
	 * Delete this record from a data source.
	 */
	public function delete() {
		if($this->isSaved())
			$this->is_deleted = TRUE;
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
