<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A base model definition.
 */
abstract class ModelDefinition {
	
	protected $model_name,
	          $model_loader,
	          $description;
	
	public function __construct($name, Loader $model_loader) {
		$this->model_name = $name;
		$this->model_loader = $model_loader;
	}
	
	public function __destruct() {
		unset(
			$this->model_loader, 
			$this->description
		);
	}
	
	protected function getName() {
		return $this->model_name;
	}
	
	public function getRecord(array &$data = array()) {
		return new InnerRecord($data);
	}
	
	public function getRecordIterator(RecordIterator $it) {
		return $it;
	}
	
	public function getDescription() {
		if(NULL === $this->description)
			$this->description = $this->describe();
		
		return $this->description;
	}
	
	abstract public function getModelGateway(DataSource $ds);
	abstract protected function describe();
	abstract public function getValidator();
}
