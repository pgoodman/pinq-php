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
		$class_prefixes = array(
			$this->config['config']['class_prefix'], 
			'Pinq',
		);
		
		$package_file = NULL;
		$class = '';
		
		do {
			$folder = array_shift($search_where);
			$base_dir = '/packages/';
			$prefix = array_shift($class_prefixes);
			$path = explode('.', $key);
			
			// this seems redundant, but the separation of $folder and $base_
			// dir is actually useful for later if we want to include a system
			// directory
			$abs_dir = $folder . $base_dir;
			
			// go as deep as we can into the directory structure based on the
			// path to the package given
			while(!empty($path) && is_dir($abs_dir .'/'. $path[0])) {
				$part = array_shift($path);
				$class .= "_{$part}";
				
				$base_dir .= '/'. $part;
				$abs_dir .= '/'. $part;
			}
						
			// see if we can configure a multi-file package using an
			// __init__ file
			if(file_exists($abs_dir .'/__init__.php')) {
				$package_file = $base_dir .'/__init__.php';
				break;
			
			// the package wasn't spread across multiple files, is it a
			// single file package?
			} else if(!empty($path) && file_exists($abs_dir ."/{$path[0]}.php")) {
				$package_file = $base_dir ."/{$path[0]}.php";
				$class .= "_{$path[0]}";
				break;
			}
		
		} while(!empty($search_where));
		
		// we might have included a package in the applications dir. it might
		// be dependent on the package of the same name from inside the system
		// dir, so we will include it but not configure/instantiate it.
		if(!empty($search_where)) {
			$sys_file = array_shift($search_where) . $package_file;
			if(file_exists($sys_file))
				require_once $sys_file;
		}
		
		// the final class name. this will be an amalgamation of the
		// subdirectories along the path to the package, and if the package is
		// in a single file, then it will also include the file name.
		$class = $prefix . class_name($class);
				
		// the package has no config file
		
		if(NULL === $package_file) {
			throw new InvalidPackageException(
				"Unable to load the package [{$key}]. Please make sure that ".
				"either a __init__.php file exists in the package's directory ".
				"or that a file exists with the same name as the package."
			);
		}
		
		// bring in the file that will configure itself. what's nice about
		// this way of doing things is that no naming schemes are imposed on
		// the programmer
		require_once $folder . $package_file; // include
		
		// if the package configures itself it might change this
		$package = NULL;

		// the package might be a self-configuring class. see if it has a
		// configure function.
		if(method_exists($class, 'configure')) {
			
			// package info. the class name is especially important if the
			// subclass of a system package is being used
			$package_info = array(
				'key' => $key, 
				'class' => $class,
				'argv' => $path,
				'argc' => count($path),
			);
			
			// call the packages configuration function.
			$func = new ReflectionMethod($class, 'configure');
			$package = $func->invoke(
				NULL, // because it's static
				$this, 
				$this->config, 
				array_merge($package_info, $context)
			);
		}
		
		// store the package
		$this->store($key, $package);
		
		return $package;
	}
	
	/**
	 * Store a package.
	 */
	public function store($key, $val = NULL) {
		$this->offsetSet($key, $val);
	}
}
