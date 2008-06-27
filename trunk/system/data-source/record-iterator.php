<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

interface RecordIterator extends Countable, SeekableIterator {
	
}

/**
 * A set of data records.
 * @author Peter Goodman
 */
abstract class InnerRecordIterator implements RecordIterator {
	
	protected $key;
	
	/**
	 * Constructor, set the offset and count.
	 */
	public function __construct() {
		$this->key = 0;
	}
	
	/**
	 * Seek to a specific index in the iterator.
	 */
	public function seek($key) {
		
		// make sure the key is in the right place
		if($key < 0 || $key >= $this->count()) {
			throw new OutOfBoundsException(
				"Could not access row [{$key}] of record set."
			);
		}
		
		$this->key = $key;
	}
	
	/**
	 * Return the current key.
	 */
	public function key() {
		return $this->key;
	}
	
	/**
	 * Rewind the record set.
	 */
	public function rewind() {
		if($this->key > 0)
			$this->seek($this->offset);
	}
	
	/**
	 * Is the current row valid?
	 */
	public function valid() {
		return $this->key < $this->count();
	}
	
	/**
	 * Move to the next row.
	 */
	public function next() {
		$this->key++;
	}
}

/**
 * An iterator to handle an inner record iterator.
 * @author Peter Goodman
 */
abstract class OuterRecordIterator extends IteratorIterator implements RecordIterator {
		
	/**
	 * Constructor, bring in the record iterator, the constructor is overridden
	 * to force a RecordIterator to be accepted.
	 */
	public function __construct(RecordIterator $it) {
		parent::__construct($it);
	}
	
	/**
	 * Get the inner record iterator.
	 */
	public function getInnerRecordIterator() {
		return $this->getInnerIterator();
	}
	
	/**
	 * Seek to an offset.
	 */
	public function seek($offset) {
		return $this->getInnerIterator()->seek($offset);
	}
	
	/**
	 * Return the row count.
	 */
	public function count() {
		return $this->getInnerIterator()->count();
	}
}

/**
 * A record iterator that is like an outer-record-iterator but also brings in
 * a gateway to do all model-related things.
 * @author Peter Goodman
 */
abstract class GatewayRecordIterator extends OuterRecordIterator {
	
	protected $gateway;
	
	public function __construct(RecordIterator $it, Gateway $gateway) {
		parent::__construct($it);
		$this->gateway = $gateway;
	}
}

