<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A SQLite result set.
 *
 * @author Peter Goodman
 */
class PinqDbSqliteRecordIterator extends PinqDbRecordIterator {
	
	// the number of rows in this result set
	protected $_count,
	          $_result;
	
	/**
	 * PinqDbSqliteRecordIterator(SQLiteResult)
	 */
	public function __construct(SQLiteResult $result) {
		
		$this->_result = $result;
		
		$this->_count = $result->numRows()
		 or $this->_count = 0;
		
		// this is after because RecordIterator calls $this->_count
		parent::__construct($result);
	}
	
	/**
	 * $i->count(void) <==> count($i) -> int
	 *
	 * Return how many rows there are in the result set.
	 */
	public function count() {
		return $this->_count;
	}
	
	/**
	 * $i->seek(int) -> void
	 *
	 * Seek to an arbitrary place in the mysql result set.
	 */
	public function seek($key) {
		$curr_key = $this->key();
		parent::seek($key);
		
		if($key != $curr_key)
			$this->_result->seek($key);
	}
	
	/**
	 * $i->fetch(void) -> array
	 */
	protected function fetch() {
		return $this->_result->fetch();
	}
}
