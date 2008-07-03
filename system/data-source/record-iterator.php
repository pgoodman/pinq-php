<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

interface RecordIterator extends Countable, SeekableIterator {
	
}

/**
 * Iterator for a set of records from a data source.
 *
 * @author Peter Goodman
 */
abstract class InnerRecordIterator implements RecordIterator {
	
	protected $_key;
	
	/**
	 * InnerRecordIterator(void)
	 */
	public function __construct() {
		$this->_key = 0;
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
		if($key < 0 || $key >= $this->count()) {
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
		if($this->_key > 0)
			$this->seek(0);
	}
	
	/**
	 * $i->valid(void) -> bool
	 *
	 * Check if the row in the record iterator represented by the current row
	 * id exists. If it doesn't this will return FALSE.
	 */
	public function valid() {
		return $this->_key < $this->count();
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
 */
class OuterRecordIterator extends IteratorIterator implements RecordIterator {
		
	/**
	 * OuterRecordIterator(RecordIterator)
	 *
	 * Bring in the record iterator, the constructor is overridden such that
	 * this class requires a RecordIterator.
	 */
	public function __construct(RecordIterator $it) {
		parent::__construct($it);
	}
	
	/**
	 * $i->getRecordIterator(void) <==> $i->getInnerIterator(void) 
	 * -> RecordIterator
	 *
	 * Get the inner record iterator.
	 */
	public function getRecordIterator() {
		return $this->getInnerIterator();
	}
	
	/**
	 * $i->seek(int) -> void
	 *
	 * Seek the inner record iterator to a specific row.
	 */
	public function seek($offset) {
		return $this->getInnerIterator()->seek($offset);
	}
	
	/**
	 * $i->count(void) -> int
	 *
	 * Return the number of records in the inner record iterator.
	 */
	public function count() {
		return $this->getInnerIterator()->count();
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
	
	protected $gateway;
	
	/**
	 * GatewayRecordIterator(RecordIterator, Gateway)
	 */
	public function __construct(RecordIterator $it, Gateway $gateway) {
		parent::__construct($it);
		$this->gateway = $gateway;
	}
	
	/**
	 */
	public function __destruct() {
		unset($this->gateway);
	}
}

