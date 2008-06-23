<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A base model definition.
 */
abstract class DatabaseModelDefinition extends ModelDefinition {
	
	public function getRecord(array &$data = array()) {
		return new InnerRecord($data);
	}
	
	public function getValidator() {
		return NULL;
	}
	
	public function getModelGateway(DataSource $ds, Dictionary $models) {
		return new DatabaseModelGateway($ds, $models);
	}
}
