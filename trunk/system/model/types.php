<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

function int($length = 0) {
	return array(ModelDefinition::TYPE_INT, $length, NULL);
}

function float($length = 0) {
	return array(ModelDefinition::TYPE_FLOAT, $length, NULL);
}

function bool($default = FALSE) {
	return array(ModelDefinition::TYPE_BOOL, 1, $default);
}

function string($length = 0, $default = NULL) {
	return array(ModelDefinition::TYPE_STRING, $length, $default);
}

function text() {
	return array(ModelDefinition::TYPE_STRING, 0, NULL);
}

function enum() {
	$default = func_get_args();
	return array(ModelDefinition::TYPE_ENUM, 0, $default);
}

function blob() {
	return array(ModelDefinition::TYPE_BINARY, 0, NULL);
}

function binary() {
	return array(ModelDefinition::TYPE_BINARY, 0, NULL);
}
