<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A gateway to the records in a given data source.
 * @author Peter Goodman
 */
abstract class RecordGateway {
	
	// the data source
	protected $ds;
	
	/**
	 * Constructor, bring in the data source.
	 */
	public function __construct(DataSource $ds) {
		$this->ds = $ds;
	}
	
	/**
	 * Compile a query for a specific data source.
	 */
	abstract protected function compileQuery(AbstractQuery $query);
	
	/**
	 * Get a string representation for a datasource-specic query.
	 */
	protected function getQuery($query) {
		if($query instanceof AbstractQuery)
			return $this->compileQuery($query);
		
		return (string)$query;
	}
}
