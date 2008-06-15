<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A set of data records.
 * @author Peter Goodman
 */
abstract class RecordIterator implements Countable, SeekableIterator {
	
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
 * An iterator to handle an inner record iterator. This has the same functionality
 * as an IteratorIterator, although unfortunately PHP doesn't support multiple
 * inheritance.
 * @author Peter Goodman
 */
abstract class OuterRecordIterator extends RecordIterator implements OuterIterator {
	
	protected $it;
	
	/**
	 * Constructor, bring in the record iterator.
	 */
	public function __construct(RecordIterator $record_set) {
		parent::__construct();
		$this->it = $record_set;
	}
	
	/**
	 * Get the inner record iterator.
	 */
	public function getInnerIterator() {
		return $this->it;
	}
	
	/**
	 * RecordIterator methods...
	 */
	public function current() { return $this->it->current(); }
	public function key() { return $this->it->key(); }
	public function next() { return $this->it->next(); }
	public function valid() { return $this->it->valid(); }
	public function seek($key) { return $this->it->seek($key); }
	public function limit($offset, $limit = NULL) { 
		return $this->it->limit($offset, $limit);
	}
}
