<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqInputDictionary extends Dictionary implements Package, Factory {
	
	static public $_class;
	
	/**
	 * PinqInputDictionary::factory(...) -> PinqInputDictionary
	 *
	 * Factory to instantiate an instance of this class.
	 */
	static public function factory() {
		$class = self::$_class;	
		$args = func_get_args();
		return new $class($args[0]);
	}
	
}