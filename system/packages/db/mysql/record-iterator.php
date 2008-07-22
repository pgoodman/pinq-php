<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A MySQL record set.
 *
 * @author Peter Goodman
 */
class MysqlRecordIterator extends PinqDbRecordIterator {
	
	// the number of rows in this result set
	protected $count,
	          $result;
	
	/**
	 * MysqlRecordIterator(resource)
	 */
	public function __construct($result) {
		
		$this->result = $result;
		$this->count = mysql_num_rows($result)
		 or $this->count = 0;
		
		// this is after because RecordIterator calls $this->count
		parent::__construct($result);
	}
	
	/**
	 * $i->count(void) <==> count($i) -> int
	 *
	 * Return how many rows there are in the result set.
	 */
	public function count() {
		return $this->count;
	}
	
	/**
	 * $i->seek(int) -> void
	 *
	 * Seek to an arbitrary place in the mysql result set.
	 */
	public function seek($key) {
		$curr_key = $this->key();
		parent::seek($key);
		
		if($curr_key != $key)
			mysql_data_seek($this->result, $key);
	}
	
	/**
	 * $i->fetch(void) -> array
	 */
	public function fetch() {
		return mysql_fetch_assoc($this->result);
	}
}
