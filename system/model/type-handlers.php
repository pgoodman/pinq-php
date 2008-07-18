<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class GatewayIdentityHandler implements EndpointHandler {
	final public function handle($item) { 
		return $item; 
	}
}

abstract class GatewayTypeHandler implements IntermediateHandler {
	
	protected $_gateway;
	
	public function __destruct() {
		unset($this->_gateway);
	}
	
	public function setGateway(Gateway $gateway) {
		$this->_gateway = $gateway;
	}
	
	abstract public function handle($query, $type, array &$args);
}

class RelationalGatewayQueryHandler extends GatewayTypeHandler {
	
	protected $_cached_queries = array(),
	          $_query_types;
	
	public function __construct() {
		$this->_query_types = array(
			'insert' => QueryCompiler::INSERT,
			'update' => QueryCompiler::UPDATE,
			'delete' => QueryCompiler::DELETE,
			'select' => QueryCompiler::SELECT,
			'selectAll' => QueryCompiler::SELECT,
		);
	}
	
	public function handle($query, $type, array &$args) {
		
		$query_id = $query->getId();
		
		$compile_type = $this->_query_types[$type];
		$is_set_type = $compile_type & (
			QueryCompiler::INSERT | QueryCompiler::UPDATE
		);
		
		// the query has already been compiled and cached, use it.
		if(!$is_set_type && $query->isCompiled()) {
			if(isset($this->_cached_queries[$type][$query_id]))
				return $this->_cached_queries[$type][$query_id];
		}
		
		// TODO: putting this here seems like a hack
		if($type == 'select')
			$query->limit(1);
		
		// compile the query
		$compiler = $this->_gateway->getPqlQueryCompiler();
		$this->_cached_queries[$type][$query_id] = $compiler->compile(
			$query,
			$compile_type,
			$args
		);
		
		// tell the query and its predicates that it has been compiled
		// it is done after the query has been compiled because the
		// query compiler might add in predicates.
		$query->setCompiled();
		
		return $this->_cached_queries[$type][$query_id];
	}
}

class RelationalGatewayQueryPredicatesHandler extends GatewayTypeHandler {
	
	public function handle($qp, $type, array &$args) {
		
		if(NULL === $qp->getQuery()) {
			
			$query = $this->_gateway->createPqlQuery();
			
			if($query instanceof QueryPredicates)
				$query = $query->getQuery();
			
			$predicates = $query->getPredicates();

			// the partial query has no predicates, this is easy
			if(NULL == $predicates)
				$query->setPredicates($qp);
			
			// the partial query has predicates, merge $query into the
			// predicates of the partial query
			else
				$predicates->merge($qp);
			
		} else
			$query = $qp->getQuery();
		
		return $query;
	}
}

class RelationalGatewayRecordHandler extends GatewayTypeHandler {
	
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

class GatewayStringHandler extends GatewayIdentityHandler { }

class GatewayArrayHandler extends GatewayIdentityHandler { }
