<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for data sources.
 *
 * @author Peter Goodman
 */
interface DataSource {
	
	/**
	 * $d->open(string $name) -> mixed resource
	 *
	 * Open a connection to a data source.
	 */
	public function open($name);
	
	/**
	 * $d->close(void) -> void
	 *
	 * Close the connection to a data source.
	 */
	public function close();
	
	/**
	 * $d->select(string $query[, array $args]) -> result resource
	 *
	 * Select rows from a data source, The args are substituted in for ?'s and
	 * keyed subsitiuted (eg: :key) in the query.
	 */
	public function select($query, array $args = array());
	
	/**
	 * $d->update(string $query[, array $args]) -> void
	 *
	 * Insert/update/delete/replace rows in a data source. The args are 
	 * substituted in for ?'s and keyed subsitiuted (eg: :key) in the query.
	 */
	public function update($query, array $args = array());
	
	/**
	 * $d->getDefaultRecordClass(void) -> string
	 */
	public function getDefaultRecordClass();
	
	/**
	 * $d->getDefaultRecordIteratorClass(void) -> string
	 */
	public function getDefaultRecordIteratorClass();
}
