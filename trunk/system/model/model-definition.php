<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A base model definition.
 */
abstract class ModelDefinition {
	
	private $model_name;
	
	public function __construct($name) {
		$this->model_name = $name;
	}
	
	protected function getName() {
		return $this->model_name;
	}
	
	abstract public function describe();
	abstract public function getRecord(array &$data = array());
	abstract public function getValidator();
	abstract public function getModelGateway(DataSource $ds, Dictionary $models);
}
