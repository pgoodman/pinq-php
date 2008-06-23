<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A base model definition.
 */
abstract class DatabaseModelDefinition implements ModelDefinition {
		
	public function getRecordIterator($resource) {
		return new DatabaseRecordIterator($resource);
	}
	
	public function getRecord(array &$data = array()) {
		return new InnerRecord($data);
	}
	
	public function getValidator() {
		return NULL;
	}
}
