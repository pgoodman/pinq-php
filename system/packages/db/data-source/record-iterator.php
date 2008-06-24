<?php

/**
 * A database record iterator that also splits up query data and tries to sort
 * out individual model information (if a PQL query was performed).
 * @author Peter Goodman
 */
class DatabaseRecordIterator extends OuterRecordIterator {
	
	private $_models;
	
	/**
	 * Constructor, bring in the database iterator along with the models.
	 */
	public function __construct(RecordIterator $it, Dictionary $models) {
		parent::__construct($it);
		$this->_models = $models;
	}
	
	/**
	 * Return a single record that possibly has multipel sub-records in it.
	 */
	public function current() {
		
		$data = parent::current();
		$record = NULL;
		
		// from a PQL query
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
				$definition = $this->_models[$model_name];
				$record_data = array();
				
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
					$record_data[$key] = &$temp[$original_key];
					
					// memoize the data into the base array. this is so that
					// we don't need to overwrite any Dictionary methods
					// which is very convenient.
					$data[$key] = &$record_data[$key];
				}
				
				// get the record, stored as $record so that if there is only
				// one record it will carry down nicely to the return
				$record_class = $definition->getRecordClass();
				
				$record = new $record_class($record_data);
				$record->setName($model_name);
				$records[$model_name] = $record;
				
				// this is needed so that the referenced model to the above
				// dictionary doesn't carry over to the next iteration
				unset($record_data);
				
			} while(!empty($keys));
						
			// dealing with multple records, create the base one
			if(count($records) > 1) {
				$record = new DatabaseRecord($data);
				$record->setSubRecords($records);
			
			// no longer needed, $record will fall through nicely thanks to
			// php not having lexical scoping (which I actually would prefer)
			} else
				unset($records);
		
		// the default one, unnamed and with no bells & whistles :P
		} else
			$record = new DatabaseRecord($data);
		
		// note: $record falls through nicely from the splitting up of models
		//       when parsing a pql query if there is only one record to
		//       parse out.
		return $record;
	}
}