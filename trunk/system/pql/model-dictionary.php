<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Decorator for the dictionary that adds semantic meaning to adding models
 * to the dictionary.
 * @author Peter Goodman
 */
class ModelDictionary extends Dictionary {
	
	/**
	 * This is more for semantic meaning than actual practical use.
	 */
	public function create($key, AbstractModel $model) {		
		$this->offsetSet($key, $model);
	}
}
