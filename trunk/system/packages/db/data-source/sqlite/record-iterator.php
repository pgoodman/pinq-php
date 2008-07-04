<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A SQLite result set.
 * @author Peter Goodman
 */
class SqliteRecordIterator extends InnerRecordIterator {
	
	// the number of rows in this result set
	protected $_count,
	          $_result;
	
	/**
	 * Constructor, bring in the result.
	 */
	public function __construct(SQLiteResult $result) {
		
		$this->_result = $result;
		
		$this->_count = $result->numRows()
		 or $this->_count = 0;
		
		// this is after because RecordIterator calls $this->_count
		parent::__construct($result);
	}
	
	/**
	 * Return how many rows there are in the result set.
	 */
	public function count() {
		return $this->_count;
	}
	
	/**
	 * Seek to an arbitrary place in the mysql result set.
	 */
	public function seek($key) {
		$curr_key = $this->key();
		parent::seek($key);
		
		if($key != $curr_key)
			$this->_result->seek($key);
	}
	
	/**
	 * Return a record for the current mysql row.
	 */
	public function current() {
		return $this->_result->fetch();
	}
}
