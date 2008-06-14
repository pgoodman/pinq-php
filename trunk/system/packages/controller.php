<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a request to a certain subset of events.
 * @author Peter Goodman
 */
abstract class Pinq_Controller implements Package {
	
	// package loader
	protected $packages;
	
	/**
	 * Constructor. Constructor is final because I want to encourage people to
	 * use the initialize() hook instead so that they don't forget to bring in
	 * the package loader dependency.
	 */
	final public function __construct(Loader $package_loader) {
		$this->packages = &$package_loader;
		$this->initialize(); // hook
	}
	
	/**
	 * Destructor, call a hook.
	 */
	final public function __destructor() {
		unset($this->packages);
		$this->destroy(); // hook
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
			$return[] = $this->packages->load($package_name);
				
		// hook
		$this->afterImport();
		
		// return either the array of loaded services or a single service
		// note: any one of them can be NULL
		return 1 == count($return) ? $return[0] : $return;	
	}
	
	/**
	 * Index method of a page controller.
	 */
	abstract public function index();
	
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
