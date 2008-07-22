<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqModelRelationalHandlerQuery extends GatewayTypeHandler
                                      implements InstantiablePackage {
	
	protected $_cached_queries = array(),
	          $_query_types;
	
	public function __construct() {
		$this->_query_types = array(
			self::PUT => Query::INSERT,
			self::POST => Query::UPDATE,
			self::DELETE => Query::DELETE,
			self::GET_ONE => Query::SELECT,
			self::GET_MANY => Query::SELECT,
		);
	}
	
	public function handle($query, $type, array &$args) {
		
		$query_id = $query->getId();
		
		$compile_type = $this->_query_types[$type];
		$is_set_type = $compile_type & (
			Query::INSERT | Query::UPDATE
		);
		
		// the query has already been compiled and cached, use it.
		if(!$is_set_type && $query->isCompiled()) {
			if(isset($this->_cached_queries[$type][$query_id]))
				return $this->_cached_queries[$type][$query_id];
		}
		
		// TODO: putting this here seems like a hack
		if($type & GatewayTypeHandler::GET_ONE)
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