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
	 * FieldType::int() -> array
	 */
	static public function int(array $v = array()) {
		return array(ModelDefinition::TYPE_INT, array_merge(
			array(
			'default' => NULL,
			),
			$v
		));
	}

	/**
	 * FieldType::float() -> array
	 */
	static public function float(array $v = array()) {
		return array(ModelDefinition::TYPE_FLOAT, array_merge(
			array(
			'default' => NULL,
			),
			$v
		));
	}

	/**
	 * FieldType::bool([bool $default]) -> array
	 */
	static public function bool($default = FALSE) {
		return array(ModelDefinition::TYPE_BOOLEAN, array(
			'default' => (bool)$default,
		));
	}

	/**
	 * FieldType::string([array $v]]) -> array
	 */
	static public function string(array $v = array()) {
		return array(ModelDefinition::TYPE_STRING, array_merge(
			array(
				'default' => NULL,
			),
			$v
		));
	}

	/**
	 * FieldType::text(]) -> array
	 */
	static public function text() {
		return array(ModelDefinition::TYPE_STRING, NULL);
	}

	/**
	 * FieldType::enum([mixed $val1[, mixed $val2[, ...]]]) -> array
	 */
	static public function enum() {
		$default = func_get_args();
		return array(ModelDefinition::TYPE_ENUM, array(
			'default' => $default,
		));
	}

	/**
	 * FieldType::blob(void) <==> binary(void) -> array
	 */
	static public function blob() {
		return array(ModelDefinition::TYPE_BINARY, NULL);
	}

	/**
	 * FieldType::binary(void) <==> blob(void) -> array
	 */
	static public function binary() {
		return array(ModelDefinition::TYPE_BINARY, NULL);
	}
}
