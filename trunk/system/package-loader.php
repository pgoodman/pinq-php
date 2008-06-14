<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Import packages.
 * @author Peter Goodman
 */
class PackageLoader extends Loader {
	
	protected $config;
	
	/**
	 * Constructor, bring in the config parser.
	 */
	public function __construct(Loader $config) {
		$this->config = $config;
	}
	
	/**
	 * Destructor.
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->config);
	}
	
	/**
	 * Load and configure a package. Context becomes arguments to pass to the
	 * class controller if it exists.
	 */
	public function &load($key, array $context = array()) {
		
		// packages are given as dir.subdir.subdir. etc
		$key = strtolower($key);
		
		// have we already loaded this package?
		if(NULL !== ($cached = $this[$key]))
			return $cached;
		
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
		
		$config = $this->config;
		$loader = $this;
		
		// bring in the file that will configure itself. what's nice about
		// this way of doing things is that no naming schemes are imposed on
		// the programmer
		include $package_file;
		
		// the package might be a self-configuring class. see if it has a
		// configure function.
		if(NULL !== $class) {
			if(method_exists($class, 'configure')) {
				
				// package info. the class name is especially important if the
				// subclass of a system package is being used
				$package_info = array(
					'key' => $key, 
					'class' => $class,
				);
				
				// call the packages configuration function.
				call_user_func_array(
					array($class, 'configure'), 
					array($this, $package_info, $context)
				);
			}
		}
		
		// there are no guarantees about if the package stored itself into the
		// loader or not. thus, this could easily be null. also, it's set to
		// a variable as we want to pass it back by reference.
		$ret = $this->offsetGet($key);
		
		return $ret;
	}
	
	/**
	 * Store a package.
	 */
	public function store($key, $val = NULL) {
		$this->offsetSet($key, $val);
	}
}
