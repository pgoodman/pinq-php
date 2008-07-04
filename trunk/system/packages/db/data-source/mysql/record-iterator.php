<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A MySQL result set.
 * @author Peter Goodman
 */
class MysqlRecordIterator extends InnerRecordIterator {
	
	// the number of rows in this result set
	protected $count,
	          $result;
	
	/**
	 * Constructor, bring in the result.
	 */
	public function __construct($result) {
		
		$this->result = $result;
		$this->count = mysql_num_rows($result)
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
		$curr_key = $this->key();
		parent::seek($key);
		
		if($curr_key != $key)
			mysql_data_seek($this->result, $key);
	}
	
	/**
	 * Return a record for the current mysql row.
	 */
	public function current() {
		return mysql_fetch_assoc($this->result);
	}
}
