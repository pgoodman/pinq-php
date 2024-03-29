<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An exception thrown when a configuration file cannot be found.
 *
 * @author Peter Goodman
 */
class ConfigurationException extends InternalErrorResponse {
	
}

/**
 * Load a PHP configuration file into memory.
 *
 * @author Peter Goodman
 */
class ConfigLoader extends Loader {
	
	/**
	 * $l->load(string $key, array $context) -> array
	 *
	 * Load a configuration file from the applications folder. The context is
	 * there so that whatever is loading the config file can pass in its own
	 * variables for the configuration file to use.
	 */
	public function load($key, array $context = array()) {
		
		// get cached
		if(NULL !== ($parsed = $this[$key]))
			return $parsed;
		
		// the possible file names for this configuration file
		$files = array(
			DIR_APPLICATION ."/config/{$key}.php",
			DIR_SYSTEM ."/config/{$key}.php",
		);
				
		// figure out which file it is
		do {
			if(file_exists($file_name = array_shift($files)))
				break;
			
			$file_name = NULL;
		} while(!empty($files));
		
		// config file doesn't exist, error
		if(NULL === $file_name) {
			throw new ConfigurationException(
				"ConfigParser::load() expected a valid PHP configuration ".
				"file name (without extension). File [{$key}.php] does not ".
				"exist."
			);
		}

		$__config = array();
		
		// make the $config variable available to configuration files to
		// store configuration settings in the config loader.
		$context['config'] = &$__config;
		
		// bring in the context and include the file
		extract($context, EXTR_REFS | EXTR_OVERWRITE);
		include_once $file_name;
		
		// store and return the config info
		$this[$key] = $__config;
		
		return $__config;
	}
	
	/**
	 * $l->store(string $key, mixed $val) -> void
	 */
	public function store($key, $val = NULL) {
		
	}
}
