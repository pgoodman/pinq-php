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
	 * Constructor, bring in the database result resource.
	 */
	public function __construct($result) {
		$this->result = &$result;		
		parent::__construct();
	}
}
