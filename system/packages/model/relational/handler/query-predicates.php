<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqModelRelationalHandlerQueryPredicates extends GatewayTypeHandler 
                                                implements InstantiablePackage {
	
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