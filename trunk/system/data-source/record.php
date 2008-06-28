<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for a record.
 */
interface Record extends ArrayAccess {
	public function toArray();
}

/**
 * A data record, this implements the generic things every record needs.
 */
class InnerRecord extends Dictionary implements Record {
	
	protected $_name,
	          $_sub_records,
	          $_dirty = array();
	
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
	
	/**
	 * Overwrite some of the dictionary methods so that we can isolate new
	 * information from old information.
	 */
	public function offsetSet($key, $val) {
		$this->_dirty[$key] = $val;
		parent::offsetUnset($key);
	}
	
	/**
	 * Get some stored data.
	 */
	public function offsetGet($key) {
		if(isset($this->_dirty[$key]))
			return $this->_dirty[$key];
		
		return parent::offsetGet($key);
	}
	
	/**
	 * Does an offset exist?
	 */
	public function offsetExists($key) {
		return isset($this->_dirty[$key]) || parent::offsetExists($key);
	}
	
	/**
	 * Unset some data.
	 */
	public function offsetUnset($key) {
		unset($this->_dirty[$key]);
		parent::offsetUnset($key);
	}
	
	/**
	 * Merge the dirty into the clean, so to speak :P
	 */
	public function toArray() {
		return array_merge(parent::toArray(), $this->_dirty);
	}
	
	/**
	 * Set the sub-records for this record, thus making this record
	 * ambiguous.
	 */
	public function setSubRecords(array &$records) {
		
		if(count($records) < 2)
			return;
		
		$this->_name = NULL;
		$this->_sub_records = &$records;
	}
}

/**
 * A record holding a record. This allows stacking of records.
 */
abstract class OuterRecord implements Record {
	
	// and instance of a InnerRecord class
	protected $_inner_record;
	
	/**
	 * Bring in the record to hold.
	 */
	public function __construct(Record $record) {
		$this->_inner_record = $record;
	}
	
	/**
	 * Get the record that this outer record holds.
	 */
	final public function getInnerRecord() {
		return $this->_inner_record;
	}
	
	/**
	 * InnerRecord methods.
	 */
	public function offsetGet($key) { 
		return $this->_inner_record->offsetGet($key); 
	}
	public function offsetSet($key, $val) { 
		return $this->_inner_record->offsetSet($key, $val); 
	}
	public function offsetExists($key) { 
		return $this->_inner_record->offsetExists($key); 
	}
	public function offsetUnset($key) {
		return $this->_inner_record->offsetUnset($key);
	}
	
	public function toArray() {
		return $this->_inner_record->toArray();
	}
}
