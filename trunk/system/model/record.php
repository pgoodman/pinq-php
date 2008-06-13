<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A data record.
 */
abstract class Record extends Dictionary {
	abstract public function save();
	abstract public function delete();
}

/**
 * A record holding a record.
 */
abstract class OuterRecord extends Record {
	
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
	public function offsetGet($key) { return $this->record->offsetGet($key); }
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
