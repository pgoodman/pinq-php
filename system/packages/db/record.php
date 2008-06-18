<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a single database record.
 * @author Peter Goodman
 */
class DatabaseRecord extends AbstractRecord implements Object {
	
	// database records always exist insofar as they are only instantiated
	// through a query method
	protected $_is_saved = TRUE,
	          $_is_deleted = FALSE;
	
	// if the PQL was used, some things might be prefixed. Make sure that we
	// un-prefix them and set things up nicely.
	public function __construct(array &$data = array()) {
		
		// PQL was used in the query
		if(isset($data['__pql__'])) {
			
			$temp = $data;
			
			// we're going to change the data and memoize the combined results
			// into it with everything by reference
			$data = array();
			
			// get rid of this, we no longer need it
			unset($temp['__pql__']);
			
			$keys = array_keys($temp);
			$records = array();
			
			do {
				// we know that in a pql query, aside from the __pql__ column
				// which we've already deal with, a model name will be the
				// first column, so we'll take it off
				$model_name = substr(array_shift($keys), 2);
				$model = array();
				
				// plus one because the columns are prefixed with
				// '<model name>_'.
				$len = strlen($model_name)+1;
				
				// now that we've got a model name, we'll go and get all of
				// its columns; however, if we find what looks like another
				// model name, we stop.
				foreach($keys as $key) {
					
					// another table has been found
					if(strpos($key, '__') === 0)
						break 1;
					
					// keep track of the original key, and subtract from the
					// keys array
					$original_key = array_shift($keys);
					
					// we've found a prefixed column, chop off the prefix
					//if(strpos($key, $model_name) === 0)
					$key = substr($key, $len);
					
					// add in the data
					$model[$key] = &$temp[$original_key];
					
					// memoize the data into the base array. this is so that
					// we don't need to overwrite any Dictionary methods
					// which is very convenient.
					$data[$key] = &$model[$key];
				}
				
				// store the model
				$records[$model_name] = new self($model);
				$records[$model_name]->setName($model_name);
				
				// this is needed so that the referenced model to the above
				// dictionary doesn't carry over to the next iteration
				unset($model);
				
			} while(!empty($keys));
			
			// store the sub records
			$this->setName($model_name);
			$this->setSubRecords($records);
		}
		
		parent::__construct($data);
	}
	
	// the database record doesn't actually know anything about itself other
	// than that it's a record. these functions are mainly for other types of
	// records.
	public function save() { assert(FALSE); }
	
	// delete this row (the database record can't actually do that, though)
	public function delete() {
		$this->is_deleted = TRUE;
		$this->is_saved = FALSE;
	}
	
	public function __set($key, $val) { }
	public function __isset($key) { }
	public function __unset($key) { }
}
