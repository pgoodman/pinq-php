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
	
	protected $offset = 0,
	          $limit,
	          $key;
	
	/**
	 * Constructor, set the offset and count.
	 */
	public function __construct() {
		$this->key = $this->offset;
		$this->limit = $this->count();
	}
	
	/**
	 * Seek to a specific index in the iterator.
	 */
	public function seek($key) {
		
		// make sure the key is in the right place
		if($key < $this->offset || $key >= $this->limit) {
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
		if($this->offset > 0)
			$this->seek($this->offset);
	}
	
	/**
	 * Is the current row valid?
	 */
	public function valid() {
		return $this->key < $this->limit;
	}
	
	/**
	 * Move to the next row.
	 */
	public function next() {
		$this->key++;
	}
	
	/**
	 * Make this iterator only go over a slice of the result set. Limit does
	 * not act as an offset from the offset, it is the right-side bracket.
	 */
	public function limit($offset, $limit = NULL) {
		
		// make sure that the limit is within the proper range
		if(NULL !== $limit) {
			$count = $this->count();
			$limit = (int)$limit;
			$this->limit = ($limit <= $count) ? $limit : 
			               ($limit < 0) ? 0 : $count;
		}
		
		// make sure the offset is within the proper range
		$offset = (int)$offset;
		$offset = ($offset < 0) ? 0 : 
		          ($offset > $this->limit) ? $this->limit : $offset;
		
		$this->offset = $offset;
	}
}

/**
 * An iterator to handle an inner record iterator.
 * @author Peter Goodman
 */
abstract class OuterRecordIterator extends IteratorIterator implements RecordIterator {
	
	protected $it;
	
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
	 * RecordIterator methods...
	 */
	public function limit($offset, $limit = NULL) { 
		return $this->getInnerIterator()->limit($offset, $limit);
	}
	
	/**
	 * Seek to an offset.
	 */
	public function seek($offset) {
		return $this->getInnerIterator()->seek($offset);
	}
}
