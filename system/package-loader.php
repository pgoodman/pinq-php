<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Import packages.
 * @author Peter Goodman
 */
class PackageLoader extends Dictionary implements Loader {
	
	protected $config;
	
	/**
	 * Constructor, bring in the config parser.
	 */
	public function __construct(Loader $config) {
		$this->config = $config;
	}
	
	/**
	 * Destrcctor.
	 */
	public function __destruct() {
		unset($this->config);
	}
	
	/**
	 * Load and configure a package. Context becomes arguments to pass to the
	 * class controller if it exists.
	 */
	public function &load($key, array $context = array()) {
		
		// have we already loaded this package?
		if(NULL !== ($cached = $this[$key]))
			return $cached;
		
		// packages are given as dir.subdir.subdir. etc
		$key = strtolower($key);
		
		// search in both the system and application directories. this will
		// look first in the applications directory
		$search_where = array(DIR_APPLICATION, DIR_SYSTEM);
		$class_prefixes = array($this->config['config']['class_prefix'], '');
		
		$package_file = NULL;
		$class = NULL;
		do {
			
			$base_dir = array_shift($search_where) .'/packages/';
			$prefix = array_shift($class_prefixes);
			$path = explode('.', $key);
			
			// go as deep as we can into the directory structure based on the
			// path to the package given
			while(!empty($path) && is_dir($base_dir .'/'. $path[0]))		
				$base_dir .= '/'. array_shift($path);
		
			// see if we can configure a multi-file package using an
			// __init__ file
			if(file_exists($base_dir .'/__init__.php')) {
				$package_file = $base_dir .'/__init__.php';
				break;
			
			// the package wasn't spread across multiple files, is it a
			// single file package?
			} else if(!empty($path) && file_exists($base_dir ."/{$path[0]}.php")) {
				$package_file = $base_dir ."/{$path[0]}.php";
				$class = $prefix . class_name($path[0]);
				break;
			}
		
		} while(!empty($search_where));
		
		// the package has no config file
		if(NULL === $package_file) {
			throw new InvalidPackageException(
				"Unable to load the package [{$key}]. Please make sure that ".
				"either a __init__.php file exists in the package's directory ".
				"or that a file exists with the same name as the package."
			);
		}
		
		// set up the variables that packages that configure themselves should
		// use
		$argv = &$path;
		$argc = count($argv);
		$config = &$this->config;
		$package = NULL;
		$ignore = FALSE;
		
		// bring in the file that will configure itself. what's nice about
		// this way of doing things is that no naming schemes are imposed on
		// the programmer
		include $package_file;
		
		// the package is not self-configuring, try to instanciate we can
		// instantiate a class instead
		if(!$ignore && NULL === $package && NULL !== $class) {
			if(class_exists($class, FALSE))
				$package = call_user_class_array($class, array_values($context));
		}
		
		// store and return the package
		$this[$key] = $package;
		return $package;
	}
}
