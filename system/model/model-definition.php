<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A base model definition.
 */
interface ModelDefinition {
	public function describe();
	public function getRecordIterator($resource);
	public function getRecord(array &$data = array());
	public function getValidator();
}
