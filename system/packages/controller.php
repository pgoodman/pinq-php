<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a request to a certain subset of events.
 * @author Peter Goodman
 */
abstract class PinqController implements Package {
	
	// package loader
	protected $packages,
	          $view, // page view
	          $layout; // layout view
	
	// view-related things
	public $render_layout = TRUE,
	       $layout_file = 'default';
	
	/**
	 * Constructor. Constructor is final because I want to encourage people to
	 * use the initialize() hook instead so that they don't forget to bring in
	 * the package loader dependency.
	 */
	final public function __construct(Loader $packages, View $layout, View $page) {
		
		// package loader
		$this->packages = &$packages;
		
		// view stuff, if someone wants to change the file used by the layout
		// they need only do: $this->layout->setFile('...', View::LAYOUT);
		$this->view = $page;
		$this->layout = $layout;
		$layout->setFile($this->layout_file, 'layouts');
		
		// hook
		$this->initialize();
	}
	
	/**
	 * Destructor, call a hook.
	 */
	final public function __destruct() {		
		$this->destroy(); // hook
		unset(
			$this->packages, 
			$this->layout,
			$this->view
		);
	}
	
	/**
	 * Try to call a method of this controller.
	 */
	final public function act($request_method, $method, array $arguments) {
		
		// we're working with a valid controller, are we working with
		// a valid method?
		if(is_callable(array($this, "{$request_method}_{$method}")))
			$method = "{$request_method}_{$method}";
		
		// method to handle any unsupported / or just all request
		// methods at once
		else if(is_callable(array($this, "ANY_{$method}")))
			$method = "ANY_{$method}";		
		
		// no available action method exists
		else
			yield(ERROR_405);
		
		// call the controller's action
		$this->beforeAction();
		call_user_func_array(
			array($this, $method), 
			$arguments
		);
		$this->afterAction();
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
	 * Import a package but store it with a different name. This takes
	 * advantage of the fact that the package loader is a dictionary.
	 */
	public function importAs($package_name, $alias) {
		
		// this is here because the package loader is a Loader first, and then
		// a dictionary.
		PINQ_DEBUG && assert('$this->_packages instanceof Dictionary');
		
		$package = $this->packages->load($package_name);
		$this->packages[$alias] = $package;
		return $package;
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
