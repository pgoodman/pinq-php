<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class that encompasses the general operations that can be performed on a
 * data source.
 *
 * @author Peter Goodman
 */
abstract class Gateway {

	protected $_type_handler,
	          $_resource;
	
	/**
	 * Gateway(Resource, TypeHandle)
	 */
	public function __construct(Resource $resource, PinqTypeHandler $types) {
		$this->_type_handler = $types;
		$this->_resource = $resource;
		$this->__init__();
	}
	
	/**
	 */
	public function __destruct() {
		$this->__del__();
		unset(
			$this->_type_handler,
			$this->_resource
		);
	}
	
	/**
	 * $g->handleInput(mixed $input, string $query_type[, array &$args])
	 * -> mixed
	 * ! InvalidArgumentException
	 * 
	 * Given input, find handlers within the PinqTypeHandler that can transform
	 * it. The input will be transformed until an EndpointHandler is used.
	 */
	protected function handleInput($input, $query_type, array &$args = array()) {
		
		while(NULL !== ($handler = $this->_type_handler->getHandler($input))) {
			
			if($handler instanceof GatewayTypeHandler)
				$handler->setGateway($this);
		
			$input = $handler->handle($input, $query_type, $args);
		
			if($handler instanceof EndpointHandler)
				return $input;
		}
		
		// endpoint wasn't found
		throw new InvalidArgumentException(
			"Unsupported type passed as argument to Gateway::{$query_type}()."
		);
	}
	
	/**
	 * $g->select(mixed $what[, array $using]) -> {Record, NULL}
	 *
	 * Get a single record from the data source.
	 */
	public function select($what, array $using = array()) {
		return $this->_resource->GET(
			$this->handleInput($what, GatewayTypeHandler::GET_ONE, $using)
		);
	}
	
	/**
	 * $g->selectAll(mixed $what[, array $using]) -> RecordIterator
	 *
	 * Get a record set from the data source and wrap it in a record iterator.
	 */
	public function selectAll($what, array $using = array()) {
		return $this->_resource->GET(
			$this->handleInput($what, GatewayTypeHandler::GET_MANY, $using)
		);
	}
	
	/**
	 * $g->delete(mixed $what[, array $using]) -> mixed
	 *
	 * Delete a record from the data source.
	 */
	public function delete($what, array $using = array()) {
		return $this->_resource->DELETE(
			$this->handleInput($what, GatewayTypeHandler::DELETE, $using)
		);
	}
	
	/**
	 * $g->insert(mixed $what[, array $using]) -> mixed
	 *
	 * Insert a record into the data source.
	 */
	public function insert($what, array $using = array()) {
		return $this->_resource->PUT(
			$this->handleInput($what, GatewayTypeHandler::PUT, $using)
		);
	}
	
	/**
	 * $g->update(mixed $what[, array $using]) -> mixed
	 *
	 * Modify a record in the data source.
	 */
	public function update($what, array $using = array()) {
		return $this->_resource->POST(
			$this->handleInput($what, GatewayTypeHandler::POST, $using)
		);
	}
	
	/**
	 * $g->quote(string) -> string
	 *
	 * Make this string safe for the resource.
	 *
	 * @todo This is the one left-over of moving argument substitution into
	 *       the type handlers. It would be nice to get rid of this, although
	 *       this might have practical uses.
	 */
	public function quote($str) {
		return $this->_resource->quote($str);
	}
	
	/**
	 * $g->__init__(void) -> void
	 *
	 * Hook called after class construction.
	 */
	protected function __init__() { }
	
	/**
	 * $g->__del__(void) -> void
	 *
	 * Hook called before class resources are released.
	 */
	protected function __del__() { }
}

/**
 * Class that can also access named gateways with context specific data.
 *
 * @author Peter Goodman
 */
abstract class GatewayAggregate extends Gateway implements Named {
	
	protected $_gateways = array(),
	          $_name,
	          $_data;
	
	/**
	 * $g->getName(void) -> string
	 *
	 * Get the name of this gateway.
	 */
	public function getName() {
		return $this->_name;
	}
	
	/**
	 * $g->setName(string) -> void
	 *
	 * Set the name of this gateway.
	 */
	public function setName($name) {
		$this->_name = (string)$name;
	}
	
	/**
	 * $g->setData(array) -> void
	 *
	 * Set context-specific data for this gateway.
	 */
	public function setData(array $data) {
		$this->_data = $data;
	}
	
	/**
	 * $g->getData(void) -> array
	 *
	 * Get the context-specific data for this gateway.
	 */
	public function getData() {
		return $this->_data;
	}
	
	/**
	 * $g->__get(string $gateway_name) <==> $g->$gateway_name -> Gateway
	 *
	 * Get a named gateway.
	 */
	public function __get($gateway_name) {
		return $this->__call($gateway_name);
	}
	
	/**
	 * $g->__call(string $gateway_name[, array $data]) 
	 * <==> $g->$gateway_name(**$data) -> Gateway
	 *
	 * Get a named gateway with context specific data.
	 */
	public function __call($gateway_name, array $data = array()) {
		
		$sdata = serialize($data);
		if(isset($this->_gateways[$gateway_name][$sdata]))
			return $this->_gateways[$gateway_name][$sdata];
		
		$gateway = $this->getGateway($gateway_name);
		
		if($gateway instanceof self) {
			$gateway->setName($gateway_name);
			$gateway->setData($data);
		}
		
		return $this->_gateways[$gateway_name][$sdata] = $gateway;
	}
	
	/**
	 * $g->getGateway(string $name) -> Gateway
	 *
	 * Create a new instance of a named gateway class or this class.
	 */
	protected function getGateway($name) {
		$class = get_class($this);
		
		if($name === $this->getName())
			return $this;
		
		// load in the model if it hasn't been loaded yet
		$this->_model_dict[$name];
		
		$temp = class_name("{$name} gateway");
		
		if(class_exists($temp, FALSE))
			$class = $temp;
		
		return new $class(
			$this->_resource, 
			$this->_type_handler
		);
	}
}

/**
 * Class to handle incoming types to a gateway.
 *
 * @author Peter Goodman
 */
abstract class GatewayTypeHandler implements IntermediateHandler {
	
	const GET_ONE = 1,
	      GET_MANY = 2,
	      PUT = 4,
	      POST = 8,
	      DELETE = 16;
	
	protected $_gateway;
	
	public function __destruct() {
		unset($this->_gateway);
	}
	
	public function setGateway(Gateway $gateway) {
		$this->_gateway = $gateway;
	}
	
	abstract public function handle($query, $type, array &$args);
}

/**
 * Handle an endpoint type that returns itself.
 * 
 * @author Peter Goodman
 */
abstract class GatewayIdentityHandler extends GatewayTypeHandler
                                      implements EndpointHandler {
	
	final public function handle($item, $type, array &$args) { 
		return $item; 
	}
}
