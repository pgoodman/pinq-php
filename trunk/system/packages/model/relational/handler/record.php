<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqModelRelationalHandlerRecord extends GatewayTypeHandler 
                                       implements InstantiablePackage {
	
	protected $_cached_queries = array();
	
	public function handle($record, $type, array &$args) {
		
		if($type == 'insert') {
			
			if(NULL === ($model_name = $record->getName())) {
				if(NULL === ($model_name = $this->_gateway->getName())) {
					throw new InvalidArgumentException(
						"RelationalModelGateway::insert() expects either ".
						"named record or for the gateway to be named when a ".
						"Record object is passed."
					);
				}
			}
			
			// build a query
			return from($model_name)->set($record->toArray());
			
		} else {
		
			// the problem is that we need a partial query to do a query pivot
			// a partial query comes from when use use __call or __get on the
			// model gateway, which returns a new model gateway. If they
			// haven't done this then they are not allowed to pass a record
			// to the query.
			if(NULL === ($gateway_name = $this->_gateway->getName())) {
				throw new InvalidArgumentException(
					"Cannot pivot on ambiguous model gateway."
				);
			}
		
			// we want to get at the innermost record
			while($record instanceof OuterRecord)
				$record = $record->getRecord();
		
			// get the model name that this record belongs to
			$model_name = $record->getName();
		
			// we can't work with an ambiguous record
			if(NULL === $model_name) {
				throw new InvalidArgumentException(
					"Cannot build relationship off of ambiguous record. If ".
					"this record has sub-records (ie: the PQL query selected ".
					"from multiple models at once) then the relationship ".
					"needs to be called on one of the sub records."
				);
			}
		
			// if we haven't already created a query for this relation, then
			// do so. the reason why these types of relations are cached is
			// because if they are called on in a loop then this function
			// would otherwise be very slow
			if(!isset($this->_cached_queries[$model_name][$type])) {
			
				$query = $this->_gateway->createPqlQuery();
			
				if($query instanceof QueryPredicates)
					$query = $query->getQuery();
			
				// add in the predicates to make linking and pivoting to the
				// record possible
				$query->from($model_name)->link(
					$this->_gateway->getName(), 
					$model_name, 
					Query::PIVOT_RIGHT
				);
				
				$this->_cached_queries[$model_name][$type] = $query;
			}
			
			$args = $record->toArray();
		
			return $this->_cached_queries[$model_name][$type];
		}
	}
}
