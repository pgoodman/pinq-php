<?php

/**
 * @author Peter Goodman
 */
abstract class Gateway {

	protected $_type_handler,
	          $_data_source;
	
	public function __construct(DataSource $ds, TypeHandler $types) {
		$this->_type_handler = $types;
		$this->_data_source = $ds;
		$this->__init__();
	}
	
	public function __destruct() {
		$this->__del__();
		unset(
			$this->_type_handler,
			$this->_data_source
		);
	}
	
	protected function handleInput($input, $query_type, array &$args = array()) {
	
		while(NULL !== ($handler = $this->_type_handler->getHandler($input))) {
		
			if($handler instanceof GatewayTypeHandler)
				$handler->setGateway($this);
		
			$input = $handler->handle($input, $query_type, $args);
		
			if($handler instanceof EndpointHandler)
				return $input;
		}
	
		throw new InvalidArgumentException(
			"Unsupported type passed as argument to Gateway::{$query_type}()."
		);
	}
	
	public function select($what, array $using = array()) {
		return $this->getRecord($this->_data_source->select(
			$this->handleInput($what, 'select', $using),
			$using
		));
	}
	
	public function selectAll($what, array $using = array()) {
		return $this->getRecordIterator($this->_data_source->select(
			$this->handleInput($what, 'selectAll', $using),
			$using
		));
	}
	
	public function delete($what, array $using = array()) {
		return $this->_data_source->update(
			$this->handleInput($what, 'delete', $using),
			$using
		);
	}
	
	public function insert($what, array $using = array()) {
		return $this->_data_source->update(
			$this->handleInput($what, 'insert', $using),
			$using
		);
	}
	
	public function update($what, array $using = array()) {
		return $this->_data_source->update(
			$this->handleInput($what, 'update', $using),
			$using
		);
	}
	
	/**
	 * $g->getValue(mixed $query[, array $args]) -> {string, int, void}
	 *
	 * Using the record returned from ModelGateway::get(), return the value of
	 * the first selected field or NULL if no record could be found.
	 *
	 * @example
	 *     pql query to get the number of rows in a model:
	 *         $num_records = $g->getValue(
	 *             from('model_name')->count('field_name')
	 *         );
	 */
	public function selectValue($query, array $args = array()) {
		$row = $this->select($query, $args);
		
		// oh well, no row to return
		if(NULL === $row)
			return NULL;

		// dig deep into the record if we are dealing with an outer record
		while($row instanceof OuterRecord)
			$row = $row->getRecord();
		
		// we are now likely dealing with a InnerRecord that is also a dictionary
		if($row instanceof Dictionary)
			$row = array_values($row->toArray());
		else
			$row = array_values((array)$row);
		
		// does no first element exist?
		if(!isset($row[0]))
			return NULL;
		
		return $row[0];
	}
	
	/**
	 * $g->getRecord(resource) -> Record
	 *
	 * Return a Record object.
	 */
	abstract protected function getRecord($result_resource);
	
	/**
	 * $g->getRecordIterator(resource) -> RecordIterator
	 *
	 * Return a RecordIterator object.
	 */
	abstract protected function getRecordIterator($result_resource);
	
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

abstract class GatewayGateway extends Gateway implements Named {
	
	protected $_gateways = array(),
	          $_name,
	          $_data;
	
	public function getName() {
		return $this->_name;
	}

	public function setName($name) {
		$this->_name = $name;
	}
	
	public function __get($gateway_name) {
		return $this->__call($gateway_name);
	}
	
	public function __call($gateway_name, array $data = array()) {
		
		if(isset($this->_gateways[$gateway_name]))
			return $this->_gateways[$gateway_name];
		
		$gateway = $this->createGateway($gateway_name);
		
		if($gateway instanceof self) {
			$gateway->setName($gateway_name);
			$gateway->setData($data);
		}
		
		return $this->_gateways[$gateway_name] = $gateway;
	}
	
	protected function createGateway($name) {
		$class = get_class($this);
		
		if($name === $this->getName())
			return $this;
		
		// load in the model if it hasn't been loaded yet
		$this->_model_dict[$name];
		
		$temp = class_name("{$name} gateway");
		
		if(class_exists($temp, FALSE))
			$class = $temp;
		
		return new $class(
			$this->_data_source, 
			$this->_type_handler
		);
	}
}

