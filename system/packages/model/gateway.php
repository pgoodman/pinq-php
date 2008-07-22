<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class for handling gateways that have access to the model layer.
 *
 * @author Peter Goodman
 */
class PinqModelGateway extends GatewayGateway 
                       implements InstantiablePackage {
	
	protected $_model_dict;
	
	/**
	 * $g->setModelDictionary(PinqModelDictionary) -> void
	 *
	 * Give this gateway access to all models.
	 */
	public function setModelDictionary(Dictionary $models) {
		$this->_model_dict = $models;
	}
	
	/**
	 * @see GatewayGateway::getGateway(...)
	 */
	protected function getGateway($name) {		
		$gateway = parent::getGateway($name);
		$gateway->setModelDictionary($this->_model_dict);
		return $gateway;
	}
}