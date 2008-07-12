<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a request to a certain subset of available events. Events are
 * represented by public methods prefixed with either a specific request type
 * (in uppercase) or ANY, followed by an underscore. For example: ANY_index().
 *
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
	 * PinqController(PackageLoader, PinqView $layout, PinqView $page)
	 */
	final public function __construct(Loader $packages, PinqView $layout, PinqView $page) {
		
		// package loader
		$this->packages = &$packages;
		
		// view stuff, if someone wants to change the file used by the layout
		// they need only do: $this->layout->setFile('...', PinqView::LAYOUT);
		$this->view = $page;
		$this->layout = $layout;
		$layout->setFile($this->layout_file, 'layouts');
				
		// hook
		$this->__init__();
	}
	
	/**
	 */
	final public function __destruct() {		
		$this->__del__(); // hook
		unset(
			$this->packages, 
			$this->layout,
			$this->view
		);
	}
	
	/**
	 * $c->act(string $request_method, string $action[, array $arguments], 
	 * string $view_dir) -> void
	 *
	 * Call a specific controller action. This first looks for a method named
	 * {$request_method}_{$action} and then ANY_{$action}. If neither exists
	 * a 405 Method Doesn't Exist error is yielded.
	 */
	final public function act($request_method, 
	                          $action, 
	                    array $arguments = array(),
	                          $view_dir) {
				
		// we're working with a valid controller, are we working with
		// a valid method?
		if(is_callable(array($this, "{$request_method}_{$action}"))) {
			$action = "{$request_method}_{$action}";
		
		// method to handle any unsupported / or just all request
		// methods at once
		} else if(is_callable(array($this, "ANY_{$action}")))
			$action = "ANY_{$action}";		
		
		// no available action method exists
		else
			yield(ERROR_405);
		
		// set the page view
		$this->view->setFile("{$view_dir}{$action}", PinqView::PAGE);
		
		// call the controller's action
		call_user_func_array(
			array($this, $action), 
			$arguments
		);
		
		return $action;
	}
	
	/**
	 * $c->import(string $package) -> {Package, void}
	 * $c->import([string $package1[, string $package2[, ...]]]) -> Package[]
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
	 * $c->importAs(string $package_name, string $alias) -> {Package, void}
	 *
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
	 * $c->beforeAction(void) -> void
	 *
	 * Hook called before a controller's action is dispatched.
	 */
	public function beforeAction() { }
	
	/**
	 * $c->afterAction(void) -> void
	 *
	 * Hook called after a controller's action is dispatched.
	 */
	public function afterAction() { }
	
	/**
	 * $c->beforeImport(void) -> void
	 *
	 * Hook called before packages are imported.
	 */
	protected function beforeImport() { }
	
	/**
	 * $c->afterImport(void) -> void
	 *
	 * Hook called after packages are imported.
	 */
	protected function afterImport() { }
	
	/**
	 * $c->__init__(void) -> void
	 *
	 * Hook called right after class construction.
	 */
	protected function __init__() { }
	
	/**
	 * $c->__del__(void) -> void
	 *
	 * Hook called before class resources are released.
	 */
	protected function __del__() { }
}
