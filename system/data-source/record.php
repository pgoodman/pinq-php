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
 * A generic record of data.
 *
 * @author Peter Goodman
 */
class InnerRecord extends Dictionary implements Record {
	
	protected $_name,
	          $_sub_records,
	          $_dirty = array();
	
	/**
	 * $r->setName(string $name) -> void
	 *
	 * Set the model name of this record.
	 */
	public function setModelName($name) {
		$this->_name = $name;
	}
	
	/**
	 * $r->getModelName(void) -> mixed
	 *
	 * Get the model name of this record. If this record has not been assigned
	 * a model name then this will return NULL.
	 */
	public function getModelName() {
		return $this->_name;
	}
	
	/**
	 * $r->__get(string $model_name) <==> $r->$model_name -> mixed
	 *
	 * If this is record contains one or more sub-records (models that were
	 * selected from in the query) then this allows one to access those sub-
	 * records by their model names. If no sub-record exists for a given model
	 * name then this function returns NULL.
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
	 * $r->offsetSet(string $key, mixed $val) <==> $r[$key] = $val
	 *
	 * Set some "new" data to the record. Whatever is set to the record's
	 * dictionary after instantiation is isolated as "dirty" data.
	 */
	public function offsetSet($key, $val) {
		$this->_dirty[$key] = $val;
		parent::offsetUnset($key);
	}
	
	/**
	 * $r->offsetGet(string $key) <==> $r[$key] -> mixed
	 *
	 * Get a data entry from the Record. This first looks in the "dirty" 
	 * (changed) data, and then in the unchanged data.
	 */
	public function offsetGet($key) {
		if(isset($this->_dirty[$key]))
			return $this->_dirty[$key];
		
		return parent::offsetGet($key);
	}
	
	/**
	 * $r->offsetExist($key) <==> isset($r[$key]) -> bool
	 *
	 * Check if an entry exists in this record.
	 */
	public function offsetExists($key) {
		return isset($this->_dirty[$key]) || parent::offsetExists($key);
	}
	
	/**
	 * $r->offsetUnset($key) <==> unset($r[$key]) -> void
	 *
	 * Unset an entry in the record. This removes it from both the chnaged and
	 * unchanged data sets.
	 */
	public function offsetUnset($key) {
		unset($this->_dirty[$key]);
		parent::offsetUnset($key);
	}
	
	/**
	 * $r->toArray(void) -> array
	 *
	 * Returns an associative array of the unchanged and changed data together.
	 */
	public function toArray() {
		return array_merge(parent::toArray(), $this->_dirty);
	}
	
	/**
	 * $r->setSubRecords(array &$records) -> void
	 *
	 * Set the sub-records for this record. If only one sub record is being
	 * passed in in the array of records then no action is taken. If greater
	 * than one record are passed in then this record will lose any model name
	 * it had (thus becoming ambiguous) and gain an array of sub-records.
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
 *
 * @author Peter Goodman
 */
abstract class OuterRecord implements Record {
	
	// and instance of a InnerRecord class
	protected $_inner_record;
	
	/**
	 * OuterRecord(Record)
	 *
	 * Store the inner record.
	 */
	public function __construct(Record $record) {
		$this->_inner_record = $record;
	}
	
	/**
	 * $r->getInnerRecord(void) -> Record
	 *
	 * Get the record that this outer record holds.
	 */
	final public function getInnerRecord() {
		return $this->_inner_record;
	}
	
	/**
	 * @see InnerRecord::offsetGet(...)
	 */
	public function offsetGet($key) { 
		return $this->_inner_record->offsetGet($key); 
	}
	
	/**
	 * @see InnerRecord::offsetSet(...)
	 */
	public function offsetSet($key, $val) { 
		return $this->_inner_record->offsetSet($key, $val); 
	}
	
	/**
	 * @see InnerRecord::offsetExists(...)
	 */
	public function offsetExists($key) { 
		return $this->_inner_record->offsetExists($key); 
	}
	
	/**
	 * @see InnerRecord::offsetUnset(...)
	 */
	public function offsetUnset($key) {
		return $this->_inner_record->offsetUnset($key);
	}
	
	/**
	 * @see InnerRecord::toArray(...)
	 */
	public function toArray() {
		return $this->_inner_record->toArray();
	}
}
