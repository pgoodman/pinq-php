<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A set of database records.
 * @author Peter Goodman
 */
abstract class DatabaseRecordIterator extends RecordIterator {
	
	protected $result;	
	/**
	 * Constructor, bring in the PDO statement and execute the query.
	 */
	public function __construct($result) {
		$this->result = &$result;		
		parent::__construct();
	}
}