<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A set of records from the database.
 * @author Peter Goodman
 */
final class DatabaseRecordIterator extends InnerRecordIterator {
	
	private $result,
	        $ds,
	        $count;
	
	/**
	 * Constructor, bring in the database result resource.
	 */
	public function __construct($resource, Database $ds) {
		$this->result = $resource;
		$this->count = $ds->numRows($resource);
		parent::__construct();
	}
	
	/**
	 * Get the current record.
	 */
	public function current() {
		return $this->ds->fetchRow($this->result);
	}
	
	/**
	 * Seek to some row in the result. parent::seek deals with bounds
	 * checking.
	 */
	public function seek($offset) {
		parent::seek($offset);
		$this->ds->resultSeek($offset);
	}
	
	/**
	 * Get the number of rows in the result.
	 */
	public function count() {
		return $this->count;
	}
}
