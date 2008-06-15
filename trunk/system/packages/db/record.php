<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a single database record.
 * @author Peter Goodman
 */
class DatabaseRecord extends Record {
	
	// database records always exist insofar as they are only instantiated
	// through a query method
	protected $is_saved = TRUE,
	          $is_deleted = FALSE,
	          $primary_key;
	
	// the database record doesn't actually know anything about itself other
	// than that it's a record. these functions are mainly for other types of
	// records.
	public function save() { assert(FALSE); }
	
	public function delete() {
		$this->is_deleted = TRUE;
		$this->is_saved = FALSE;
	}
}
