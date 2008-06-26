<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a request to a certain subset of events.
 * @author Peter Goodman
 */
abstract class PinqController implements Package {
	
	// package loader
	protected $_packages;
	
	/**
	 * Constructor. Constructor is final because I want to encourage people to
	 * use the initialize() hook instead so that they don't forget to bring in
	 * the package loader dependency.
	 */
	final public function __construct(Loader $package_loader) {
		$this->_packages = &$package_loader;
		$this->initialize(); // hook
	}
	
	/**
	 * Destructor, call a hook.
	 */
	final public function __destruct() {		
		$this->destroy(); // hook
		unset(
			$this->_packages, 
			$this->_view
		);
	}
	
	/**
	 * Import services. This function will deal with the configuration and
	 * caching of those services as well.
	 */
	public function import() {
		
		// hook
		$this->beforeImport();
		
		// get the list of services being requested for import
		$packages = func_get_args();
		$return = array();
	
		// go over them. if we've cached one, return it, otherwise if we need to
		// load one, pass it off to the appropriate function
		foreach($packages as $package_name)
			$return[] = $this->_packages->load($package_name);
				
		// hook
		$this->afterImport();
		
		// return either the array of loaded services or a single service
		// note: any one of them can be NULL
		return 1 == count($return) ? $return[0] : $return;	
	}
	
	/**
	 * Import a package but store it with a different name. This takes
	 * advantage of the fact that the package loader is a dictionary.
	 */
	public function importAs($package_name, $alias) {
		
		// this is here because the package loader is a Loader first, and then
		// a dictionary.
		DEBUG_MODE && assert('$this->_packages instanceof Dictionary');
		
		$package = $this->_packages->load($package_name);
		$this->_packages[$alias] = $package;
		return $package;
	}
	
	/**
	 * Get a new view.
	 */
	public function view($file_name) {
		$file_name = DIR_APPLICATION .'/views/';
	}
	
	/**
	 * Hooks.
	 */
	public function beforeAction() { }
	public function afterAction() { }
	public function beforeImport() { }
	public function afterImport() { }
	
	public function initialize() { }
	public function destroy() { }
}
