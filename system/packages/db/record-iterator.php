<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A set of records from the database.
 * @author Peter Goodman
 */
class DatabaseRecordIterator extends RecordIterator {
	
	private $result,
	        $db,
	        $count,
	        $models;
	
	/**
	 * Constructor, bring in the database result resource.
	 */
	public function __construct($resource, Database $db, Dictionary $models) {
		$this->result = $resource;
		$this->db = $db;
		$this->count = $db->numRows($this->result);
		$this->models = $models;
		
		parent::__construct();
	}
	
	/**
	 * Get the current record.
	 */
	public function current() {
		return QueryDecompiler::getRecord(
			$this->db->fetchRow($this->result), 
			$this->models
		);
	}
	
	/**
	 * Seek to some row in the result. parent::seek deals with bounds
	 * checking.
	 */
	public function seek($offset) {
		parent::seek($offset);
		$this->db->resultSeek($offset);
	}
	
	/**
	 * Get the number of rows in the result.
	 */
	public function count() {
		return $this->count;
	}
}
