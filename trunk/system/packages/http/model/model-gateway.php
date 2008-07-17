<?php

abstract class PinqHttpModelGateway implements Gateway {
	
	protected $_gateways = array();
	
	public function __construct(Datasource $ds, array $route_parts = array()) {
		
	}
}