<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class representing a RESTful resource.
 *
 * @author Peter Goodman
 */
abstract class Resource {
	
	protected $_packages,
	          $_aborted = FALSE,
	          $_file;
	
	/**
	 * Resource(Loader, string $file)
	 */
	final public function __construct(Loader $packages, $file) {
		$this->_packages = $packages;
		$this->_file = $file;
		$this->__init__();
	}
	
	/**
	 */
	final public function __destruct() {
		$this->__del__();
		unset($this->_packages);
	}
	
	/**
	 * $r->getMethod(string $request_method, string $method) 
	 * -> string
	 * ! YieldResourceException
	 *
	 * Given an action name and a request method, figure out which method to
	 * call or yield to a different resource if no method is found.
	 */
	final public function getMethod($request_method, $method) {
		
		if(method_exists($this, "{$request_method}_{$method}"))
			return "{$request_method}_{$method}";
		else if(method_exists($this, "ANY_{$method}"))
			return "ANY_{$method}";
		
		yield(ERROR_405);
	}
	
	/**
	 * $r->abort(void) -> void
	 *
	 * Tell the resource that it has been aborted.
	 */
	final public function abort() {
		$this->_aborted = TRUE;
	}
	
	/**
	 * $r->isAborted(void) -> bool
	 *
	 * Check if this resource has been aborted.
	 */
	final public function isAborted() {
		return $this->_aborted;
	}
	
	/**
	 * $r->import(string $package) -> {Package, void}
	 * $r->import([string $package1[, string $package2[, ...]]]) -> Package[]
	 *
	 * Import packages. This function will deal with the configuration and
	 * caching of those packages as well.
	 * 
	 * @example
	 *     $db = $this->import('db.blog');
	 *
	 *     list($db->cache, $db) = $this->import('cache', 'db.blog');
	 *
	 *     list($cache, $db) = $this->import('cache', 'db.blog');
	 *
	 * @see PackageLoader::load(...)
	 */
	protected function import($package_name) {
		$this->beforeImport();
		
		// get the list of services being requested for import
		$package_names = func_get_args();
		$packages = array();
		
		// go over them. if we've cached one, return it, otherwise if we need to
		// load one, pass it off to the appropriate function
		foreach($package_names as $package_name)
			$packages[] = $this->_packages->load($package_name);
		
		$this->afterImport();
		
		// return either the array of loaded services or a single service
		// note: any one of them can be NULL
		return 1 == count($packages) ? $packages[0] : $packages;	
	}
	
	/**
	 * $r->importNew(string $package_name, string $alias) -> {Package, void}
	 *
	 * Import a new instance of a package, regardless of if it's been imported
	 * yet or not.
	 *
	 * @see PackageLoader::loadNew(...)
	 */
	protected function importNew($package_name) {
		$this->beforeImport();	
		$package = $this->_packages->loadNew($package_name);
		$this->afterImport();
		
		return $package;
	}
	
	/**
	 * $r->beforeAction(string $method) -> void
	 *
	 * Hook called before a controller's action is dispatched.
	 */
	public function beforeAction($method) { }
	
	/**
	 * $r->afterAction(string $method) -> void
	 *
	 * Hook called after a controller's action is dispatched.
	 */
	public function afterAction($method) { }
	
	/**
	 * $r->beforeImport(void) -> void
	 *
	 * Hook called before packages are imported.
	 */
	protected function beforeImport() { }
	
	/**
	 * $r->afterImport(void) -> void
	 *
	 * Hook called after packages are imported.
	 */
	protected function afterImport() { }
	
	/**
	 * $r->__init__(void) -> void
	 *
	 * Hook called right after class construction.
	 */
	protected function __init__() { }
	
	/**
	 * $r->__del__(void) -> void
	 *
	 * Hook called before class resources are released.
	 */
	protected function __del__() { }
}