<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An interface for data sources.
 * @author Peter Goodman
 */
interface DataSource {
	
	// open a connection to a data source
	public function open($name);
	
	// close the connection to a data source
	public function close();
	
	// select rows from a data source
	public function select($query, array $args = array());
	
	// insert/update/delete/replace rows in a data source
	public function update($query, array $args = array());
}
