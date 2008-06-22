<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Parse INI configuration files. The parser keeps already parsed files in
 * memory (via the dictionary object).
 * @author Peter Goodman
 */
class ConfigLoader extends Loader {
	
	/**
	 * Parse a configuration file in the applications folder. The context is
	 * there so that whatever loading the config file can pass in its own
	 * variables for the configuration file to use. This is not available for
	 * INI configuration files.
	 */
	public function load($key, array $context = array()) {
		
		// get cached
		if(NULL !== ($parsed = $this[$key]))
			return $parsed;
		
		// the possible file names for this configuration file
		$files = array(
			DIR_APPLICATION ."/config/{$key}". EXT,
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
				"ConfigParser::parse() expected a valid PHP configuration ".
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
	 * Store some config stuff.
	 */
	public function store($key, $val = NULL) {
		
	}
}
