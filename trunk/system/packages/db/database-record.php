<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a single database record.
 * @author Peter Goodman
 */
class DatabaseRecord extends Record {
	
	// the database record doesn't actually know anything about itself other
	// than that it's a record. these functions are mainly for other types of
	// records.
	public function save() { assert(FALSE); }
	public function delete() { assert(FALSE); }
}