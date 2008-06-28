<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A SQLite result set.
 * @author Peter Goodman
 */
class SqliteRecordIterator extends InnerRecordIterator {
	
	// the number of rows in this result set
	protected $count,
	          $result;
	
	/**
	 * Constructor, bring in the result.
	 */
	public function __construct(SQLiteResult $result) {
		
		$this->result = $result;
		$this->count = $result->numRows()
		 or $this->count = 0;
		
		// this is after because RecordIterator calls $this->count
		parent::__construct($result);
	}
	
	/**
	 * Return how many rows there are in the result set.
	 */
	public function count() {
		return $this->count;
	}
	
	/**
	 * Seek to an arbitrary place in the mysql result set.
	 */
	public function seek($key) {
		parent::seek($key);
		$this->result->seek($key);
	}
	
	/**
	 * Return a record for the current mysql row.
	 */
	public function current() {
		echo 'here';
		return $this->result->fetch(SQLITE_ASSOC);
	}
}
