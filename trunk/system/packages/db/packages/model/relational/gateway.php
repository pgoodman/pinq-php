<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class for handling relational models. The relational model gateway is
 * coupled to the PQL classes because they are the only way to express
 * relations between models when querying the data source.
 *
 * @author Peter Goodman
 */
class PinqDbModelRelationalGateway extends PinqModelRelationalGateway {
	/**
	 * $g->select(mixed $what[, array $using]) -> {Record, NULL}
	 *
	 * Get a single record from the data source.
	 */
	public function select($what, array $using = array()) {
		$record_iterator = parent::select($what, $using);
		
		if(0 === count($record_iterator))
			return NULL;
				
		$record_iterator->rewind();
		return $record_iterator->current();
	}
	
	/**
	 * $g->getValue(mixed $query[, array $args]) -> {string, int, void}
	 *
	 * Using the record returned from ModelGateway::get(), return the value of
	 * the first selected field or NULL if no record could be found.
	 *
	 * @example
	 *     pql query to get the number of rows in a model:
	 *         $num_records = $g->getValue(
	 *             from('model_name')->count('field_name')
	 *         );
	 */
	public function selectValue($query, array $args = array()) {
		$row = $this->select($query, $args);
		
		// oh well, no row to return
		if(NULL === $row)
			return NULL;

		// dig deep into the record if we are dealing with an outer record
		while($row instanceof OuterRecord)
			$row = $row->getRecord();
		
		// we are now likely dealing with a InnerRecord that is also a dictionary
		if($row instanceof Dictionary)
			$row = $row->toArray();
		
		$row = array_values($row);
		
		// does no first element exist?
		if(!isset($row[0]))
			return NULL;
		
		return $row[0];
	}
}
