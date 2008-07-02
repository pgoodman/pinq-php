<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * int(int $length) -> array
 */
function int($length = 0) {
	return array(ModelDefinition::TYPE_INT, $length, NULL);
}

/**
 * float([int $length]) -> array
 */
function float($length = 0) {
	return array(ModelDefinition::TYPE_FLOAT, $length, NULL);
}

/**
 * bool([bool $default]) -> array
 */
function bool($default = FALSE) {
	return array(ModelDefinition::TYPE_BOOLEAN, 1, (bool)$default);
}

/**
 * string([int $length[, string $default]]) -> array
 */
function string($length = 0, $default = NULL) {
	return array(ModelDefinition::TYPE_STRING, $length, $default);
}

/**
 * text(void) -> array
 */
function text() {
	return array(ModelDefinition::TYPE_STRING, 0, NULL);
}

/**
 * enum([mixed $val1[, mixed $val2[, ...]]]) -> array
 */
function enum() {
	$default = func_get_args();
	return array(ModelDefinition::TYPE_ENUM, 0, $default);
}

/**
 * blob(void) <==> binary(void) -> array
 */
function blob() {
	return array(ModelDefinition::TYPE_BINARY, 0, NULL);
}

/**
 * binary(void) <==> blob(void) -> array
 */
function binary() {
	return array(ModelDefinition::TYPE_BINARY, 0, NULL);
}
