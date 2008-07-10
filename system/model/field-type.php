<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class that returns different model field types.
 *
 * @author Peter Goodman
 */
final class FieldType {
	
	/**
	 * FieldType::int(int $length) -> array
	 */
	static public function int($length = 0) {
		return array(ModelDefinition::TYPE_INT, $length, NULL);
	}

	/**
	 * FieldType::float([int $length]) -> array
	 */
	static public function float($length = 0) {
		return array(ModelDefinition::TYPE_FLOAT, $length, NULL);
	}

	/**
	 * FieldType::bool([bool $default]) -> array
	 */
	static public function bool($default = FALSE) {
		return array(ModelDefinition::TYPE_BOOLEAN, 1, (bool)$default);
	}

	/**
	 * FieldType::string([int $length[, string $default]]) -> array
	 */
	static public function string($length = 0, $default = NULL) {
		return array(ModelDefinition::TYPE_STRING, $length, $default);
	}

	/**
	 * FieldType::text(void) -> array
	 */
	static public function text() {
		return array(ModelDefinition::TYPE_STRING, 0, NULL);
	}

	/**
	 * FieldType::enum([mixed $val1[, mixed $val2[, ...]]]) -> array
	 */
	static public function enum() {
		$default = func_get_args();
		return array(ModelDefinition::TYPE_ENUM, 0, $default);
	}

	/**
	 * FieldType::blob(void) <==> binary(void) -> array
	 */
	static public function blob() {
		return array(ModelDefinition::TYPE_BINARY, 0, NULL);
	}

	/**
	 * FieldType::binary(void) <==> blob(void) -> array
	 */
	static public function binary() {
		return array(ModelDefinition::TYPE_BINARY, 0, NULL);
	}
}
