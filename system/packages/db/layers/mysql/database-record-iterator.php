<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A MySQL result set.
 * @author Peter Goodman
 */
class MysqlDatabaseRecordIterator extends DatabaseRecordIterator {
	
	// the number of rows in this result set
	protected $count;
	
	/**
	 * Constructor, bring in the result.
	 */
	public function __construct($result) {
		parent::__construct($result);
		
		// cache this
		$this->count = mysql_num_rows($result);
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
		mysql_data_seek($this->result, $key);
	}
	
	/**
	 * Return a record for the current mysql row.
	 */
	public function current() {
		return new MysqlRecord(mysql_fetch_assoc($this->result));
	}
}
