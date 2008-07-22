<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface for a package.
 *
 * @author Peter Goodman
 */
interface Package {
}

/**
 * Interface for a configurable package.
 *
 * @see Package
 * @author Peter Goodman
 */
interface ConfigurablePackage extends Package {
	
	/**
	 * Package::configure(Loader $loader, Loader $config, array $args) -> mixed
	 *
	 * Statically configure a package. Dependening on the implementation, this
	 * function will usually return a new instance of a package.
	 */
	static public function configure(Loader $loader, Loader $config, array $args);
}

/**
 * Interface for a package that can be instantiated without configuration.
 *
 * @author Peter Goodman
 */
interface InstantiablePackage extends Package {
	
}

/**
 * Exception thrown when a package load operation or package is somehow 
 * invalid.
 *
 * @author Peter Goodman
 */
class InvalidPackageException extends InternalErrorResponse {
	
}

/**
 * Loader that imports packages from the applications/packages and system/
 * packages directories.
 *
 * @author Peter Goodman
 */
class PackageLoader extends Loader {
	
	protected $config,
	          $search_where,
	          $class_prefixes;
	
	/**
	 * PackageLoader(Loader $config)
	 *
	 * Required a Loader instance to load configuration files.
	 */
	public function __construct(Loader $config, 
	                             array $search_where,
	                             array $class_prefixes) {
		$this->config = $config;
		$this->search_where = $search_where;
		$this->class_prefixes = $class_prefixes;
	}
	
	/**
	 */
	public function __destruct() {
		parent::__destruct();
		unset($this->config);
	}
	
	/**
	 * $p->load(string $key[, array $context]) -> mixed
	 *
	 * Load a cached or new package into memory.
	 *
	 * @see PackageLoader::loadNew(...)
	 */
	public function load($key, array $context = array()) {
		
		// have we already loaded this package?
		if($this->offsetExists($key))
			return $this[$key];
		
		return $this->loadNew($key, $context);
	}
	
	/**
	 * $p->loadNew(string $key[, array $context]) -> mixed
	 *
	 * Load and configure a package. Context becomes arguments to pass to the
	 * class controller if it exists. If the package is found in the applications
	 * directory and an equivalent package exists in the system directory then
	 * this function will include the system one first but make no attempts to
	 * configure the package.
	 */
	public function loadNew($key, array $context = array()) {
		
		// packages are given as dir.subdir.subdir. etc
		$key = strtolower($key);
		
		// search in both the system and application directories. this will
		// look first in the applications directory
		$search_where = (array)$this->search_where;
		$class_prefixes = (array)$this->class_prefixes;
		
		$package_file = NULL;
		
		while(!empty($search_where)) {
			
			$folder = array_shift($search_where);
			$prefix = array_shift($class_prefixes);
			
			$base_dir = '/';
			$path = explode('.', $key);
			$class = '';
			
			// this seems redundant, but the separation of $folder and $base_
			// dir is actually useful for later if we want to include a system
			// directory
			$abs_dir = $folder . $base_dir;
			
			// go as deep as we can into the directory structure based on the
			// path to the package given
			while(!empty($path) && is_dir("{$abs_dir}/{$path[0]}")) {
				$part = array_shift($path);
				$class .= "_{$part}";
				
				$base_dir .= "/{$part}";
				$abs_dir .= "/{$part}";
			}
						
			// see if we can configure a multi-file package using an
			// __init__ file
			if(file_exists("{$abs_dir}/__init__.php")) {
				$package_file = "{$base_dir}/__init__.php";
				break;
			
			// the package wasn't spread across multiple files, is it a
			// single file package?
			} else if(!empty($path) && file_exists("{$abs_dir}/{$path[0]}.php")) {
				$package_file = "{$base_dir}/{$path[0]}.php";
				$class .= "_{$path[0]}";
				
				// we don't want the package id to be left in $argv
				array_shift($path);
				break;
			}
		}
		
		// we might have included a package in the applications dir. it might
		// be dependent on the package of the same name from inside the system
		// dir, so we will include it but not configure/instantiate it.
		while(!empty($search_where)) {
			$sys_file = array_pop($search_where) . $package_file;
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
				
		// the interfaces that this class implements
		$interfaces = class_implements($class, FALSE);
		if(FALSE === $interfaces || !in_array('Package', $interfaces)) {
			throw new InvalidPackageException(
				"Class [{$class}] must implement interface [Package] or one ".
				"of its extending interfaces."
			);
		}
		
		// if the class implements Factory then tell it what class its factory
		// method should instantiate
		if(in_array('Factory', $interfaces) && property_exists($class, '_class')) {
			$property = new ReflectionProperty($class, '_class');
			if($property->isStatic() && $property->isPublic())
				$property->setValue(NULL, $class);
		}
		
		// the package has a configuration function. Call it.
		if(in_array('ConfigurablePackage', $interfaces)) {
			
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
		
		// the package is directly instantiatied
		} else if(in_array('InstantiablePackage', $interfaces))
			$package = call_user_class_array($class, $context);
		
		$this->offsetSet($key, $package);		
		return $package;
	}
	
	/**
	 * $p->store(string $key, mixed $val) <==> $s[$key] = $val
	 */
	public function store($key, $val = NULL) {
		$this->offsetSet($key, $val);
	}
}
