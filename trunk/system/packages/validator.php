<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class Validator {
	abstract public function validate(array $data);
}