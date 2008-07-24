<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

require_once dirname(__FILE__) .'/../gateway.php';

/**
 * Class for handling relational models. The relational model gateway is
 * coupled to the PQL classes because they are the only way to express
 * relations between models when querying the data source.
 *
 * @author Peter Goodman
 */
class PinqModelRelationalGateway extends PinqModelGateway {
	
	protected $_relations;
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();		
		unset($this->_relations);
	}
	
	/**
	 * $g->setRelations(PinqModelRelationalMap) -> void
	 *
	 * Set the relations dictionary for this gateway.
	 */
	public function setRelations(PinqModelRelationalMap $relations) {
		$this->_relations = $relations;
	}
	
	/**
	 * @see GatewayAggregate::getGateway(...)
	 */
	protected function getGateway($gateway_name) {
		$gateway = parent::getGateway($gateway_name);
		$gateway->setRelations($this->_relations);
		
		return $gateway;
	}
}