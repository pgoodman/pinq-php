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
	 * Type::int(int $length) -> array
	 */
	static public function int($length = 0) {
		return array(ModelDefinition::TYPE_INT, $length, NULL);
	}

	/**
	 * Type::float([int $length]) -> array
	 */
	static public function float($length = 0) {
		return array(ModelDefinition::TYPE_FLOAT, $length, NULL);
	}

	/**
	 * Type::bool([bool $default]) -> array
	 */
	static public function bool($default = FALSE) {
		return array(ModelDefinition::TYPE_BOOLEAN, 1, (bool)$default);
	}

	/**
	 * Type::string([int $length[, string $default]]) -> array
	 */
	static public function string($length = 0, $default = NULL) {
		return array(ModelDefinition::TYPE_STRING, $length, $default);
	}

	/**
	 * Type::text(void) -> array
	 */
	static public function text() {
		return array(ModelDefinition::TYPE_STRING, 0, NULL);
	}

	/**
	 * Type::enum([mixed $val1[, mixed $val2[, ...]]]) -> array
	 */
	static public function enum() {
		$default = func_get_args();
		return array(ModelDefinition::TYPE_ENUM, 0, $default);
	}

	/**
	 * Type::blob(void) <==> binary(void) -> array
	 */
	static public function blob() {
		return array(ModelDefinition::TYPE_BINARY, 0, NULL);
	}

	/**
	 * Type::binary(void) <==> blob(void) -> array
	 */
	static public function binary() {
		return array(ModelDefinition::TYPE_BINARY, 0, NULL);
	}
}
