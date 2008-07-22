<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for a record.
 */
interface Record extends ArrayAccess {
	public function toArray();
	public function isSaved();
	public function getUnsavedData();
	public function getSavedData();
}

/**
 * A generic record of data.
 *
 * @author Peter Goodman
 */
class InnerRecord extends Dictionary implements Record, Named {
	
	protected $_name,
	          $_sub_records,
	          $_dirty = array();
	
	/**
	 * $r->setName(string $name) -> void
	 *
	 * Set the model name of this record.
	 */
	public function setName($name) {
		$this->_name = $name;
	}
	
	/**
	 * $r->getName(void) -> mixed
	 *
	 * Get the model name of this record. If this record has not been assigned
	 * a model name then this will return NULL.
	 */
	public function getName() {
		return $this->_name;
	}
	
	/**
	 * $r->getUnsavedData(void) -> array
	 *
	 * Get the data that doesn't yet exist in the data source for this record.
	 */
	public function getUnsavedData() {
		return $this->_dirty;
	}
	
	/**
	 * $r->getSavedData(void) -> array
	 *
	 * Get the data in this record that already exists in the data source and
	 * hasn't yet been overwritten by unsaved data.
	 */
	public function getSavedData() {
		return $this->_dict;
	}
	
	/**
	 * $r->isSaved(void) -> bool
	 *
	 * Check if a record is saved.
	 */
	public function isSaved() {
		return empty($this->_dirty);
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
		if(NULL === $key) {
			
			if(!is_array($val)) {
				throw new InvalidArgumentException(
					"Record::offsetSet expected value to be array when ".
					"offset is NULL."
				);
			}
			
			$this->_dirty = array_merge($this->_dirty, $val);
		} else {
			$this->_dirty[$key] = $val;
			parent::offsetUnset($key);
		}
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
	 * &$r->offsetGetRef(string $key) <==> &$r[$key] -> mixed
	 *
	 * Get a data entry from the Record. This first looks in the "dirty" 
	 * (changed) data, and then in the unchanged data.
	 */
	public function &offsetGetRef($key) {
		if(isset($this->_dirty[$key]))
			$ret = &$this->_dirty[$key];
		else
			$ret = &parent::offsetGetRef($key);
		
		return $ret;
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
	 */
	public function __destruct() {
		unset($this->_inner_record);
	}
	
	/**
	 * $r->getRecord(void) -> Record
	 *
	 * Get the record that this outer record holds.
	 */
	final public function getRecord() {
		return $this->_inner_record;
	}
	
	/**
	 * @see InnerRecord::offsetGet(...)
	 */
	public function offsetGet($key) { 
		return $this->_inner_record->offsetGet($key); 
	}
	
	/**
	 * @see InnerRecord::offsetGetRef(...)
	 */
	public function &offsetGetRef($key) { 
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
	
	/**
	 * @see InnerRecord::toArray(...)
	 */
	public function isSaved() {
		return $this->_inner_record->isSaved();
	}
	
	/**
	 * @see InnerRecord::getUnsavedData(...)
	 */
	public function getUnsavedData() {
		return $this->_inner_record->getUnsavedData();
	}
	
	/**
	 * @see InnerRecord::getSavedData(...)
	 */
	public function getSavedData() {
		return $this->_inner_record->getSavedData();
	}
	
	/**
	 */
	public function __call($fn, array $args = array()) {
		$ref = array($this->_inner_record, $fn);
		if(is_callable($ref) || method_exists($this->_inner_record, '__call'))
			return call_user_func_array($ref, $args);
		
		return NULL;
	}
}

interface RecordIterator extends Countable, SeekableIterator {
	public function shift();
	public function pop();
}

/**
 * Iterator for a set of records from a data source.
 *
 * @author Peter Goodman
 */
abstract class InnerRecordIterator implements RecordIterator {
	
	protected $_key,
	          $_offset,
	          $_limit;
	
	/**
	 * InnerRecordIterator(void)
	 */
	public function __construct() {
		$this->_key = $this->_offset = 0;
		$this->_limit = $this->count();
	}
	
	/**
	 * $i->shift(void) -> mixed
	 *
	 * Shift off and return the first record from the record iterator. If there
	 * is no record to shift off this will return NULL.
	 */
	public function shift() {
		$this->rewind();

		if(!$this->valid())
			return NULL;
		
		$ret = $this->current();
		
		$this->_offset++;
		$this->_key++;
		
		return $ret;
	}
	
	/**
	 * $i->pop(void) -> mixed
	 *
	 * Pop off and return the last record from the record iterator. If there
	 * are no records left then this will return NULL.
	 */
	public function pop() {
		try {
			$this->seek($this->_limit - 1);
			$ret = $this->current();
			
			$this->_limit--;
			$this->rewind();
			
			return $ret;
		
		} catch(OutOfBoundsException $e) {
			return NULL;
		}
	}
	
	/**
	 * $i->seek(int $key) -> void
	 *
	 * Seek to a specific row in the iterator. Rows are indexed from [0, n-1].
	 * If the row number supplied is out of bounds, ie: it is below zero or
	 * above n-1 then a OutOfBoundsException is thrown.
	 */
	public function seek($key) {
		
		// make sure the key is in the right place
		if($key < $this->_offset || $key >= $this->_limit) {
			throw new OutOfBoundsException(
				"Could not access row [{$key}] of record set."
			);
		}
		
		$this->_key = $key;
	}
	
	/**
	 * $i->key(void) -> int
	 *
	 * Return the current row number.
	 */
	public function key() {
		return $this->_key;
	}
	
	/**
	 * $i->rewind(void) -> void
	 *
	 * Rewind the record iterator to start at row zero.
	 */
	public function rewind() {
		if($this->_key > $this->_offset)
			$this->seek($this->_offset);
	}
	
	/**
	 * $i->valid(void) -> bool
	 *
	 * Check if the row in the record iterator represented by the current row
	 * id exists. If it doesn't this will return FALSE.
	 */
	public function valid() {
		return ($this->_key < $this->count()) && 
		       ($this->_key >= $this->_offset);
	}
	
	/**
	 * $i->next(void) -> void
	 *
	 * Move the internal record pointer to the next record.
	 */
	public function next() {
		$this->_key++;
	}
}

/**
 * Class to handle stacking iterators on top of a RecordIterator.
 *
 * @author Peter Goodman
 * @note The shift/pop are also defined in here because the stacking of the
 *       iterators needs to be maintained.
 */
class OuterRecordIterator extends PinqIteratorIterator implements RecordIterator {
	
	protected $_offset,
	          $_limit;
	
	/**
	 * OuterRecordIterator(RecordIterator)
	 *
	 * Bring in the record iterator, the constructor is overridden such that
	 * this class requires a RecordIterator.
	 */
	public function __construct(RecordIterator $it) {
		parent::__construct($it);
		$this->_offset = 0;
		$this->_limit = count($it);
	}
	
	/**
	 * $i->getIterator(void) -> RecordIterator
	 *
	 * Get the inner record iterator.
	 */
	public function getIterator() {
		return $this->_iterator;
	}
	
	/**
	 * $i->shift() -> mixed
	 *
	 * Shift off the first record in the iterator.
	 */
	public function shift() {
		$this->rewind();
		$current = $this->current();
		
		$this->_offset++;
		$this->next();
		
		return $current;
	}
	
	/**
	 * $i->pop() -> mixed
	 *
	 * Pop off the last record in the iterator.
	 */
	public function pop() {
		$this->seek($this->_limit - 1);
		$current = $this->current();
		
		$this->_limit--;
		$this->rewind();
		
		return $current;
	}
	
	/**
	 * $i->rewind(void) -> void
	 */
	public function rewind() {
		if($this->key() > $this->_offset)
			$this->seek($this->_offset);
	}
	
	/**
	 * $i->valid(void) -> bool
	 */
	public function valid() {
		$key = $this->key();
		return $key >= $this->_offset && $key < $this->_limit;
	}
	
	/**
	 * $i->seek(int) -> void
	 *
	 * Seek the inner record iterator to a specific row.
	 */
	public function seek($offset) {
		return $this->_iterator->seek($offset);
	}
	
	/**
	 * $i->count(void) -> int
	 *
	 * Return the number of records in the inner record iterator.
	 */
	public function count() {
		return $this->_iterator->count();
	}
}

/**
 * A record iterator that is like an outer-record-iterator but also brings in
 * a gateway to do all model-related things.
 *
 * This class is somewhat of an anti-pattern because it implicitly means that
 * the programmer is likely to commit the N+1 query problem. However, given
 * the explicitness of how gateways are used the programmer ought realize 
 * that. This class thus must be used with a grain of salt.
 *
 * @author Peter Goodman
 */
abstract class GatewayRecordIterator extends OuterRecordIterator {
	
	protected $_gateway;
	
	/**
	 * GatewayRecordIterator(RecordIterator, Gateway)
	 */
	public function __construct(RecordIterator $it, Gateway $gateway) {
		parent::__construct($it);
		$this->_gateway = $_gateway;
	}
	
	/**
	 */
	public function __destruct() {
		unset($this->_gateway);
	}
}