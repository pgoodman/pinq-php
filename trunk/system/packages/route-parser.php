<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Deal with parsing routes. This class accepts programmer defined route
 * remappings. With these remappings, the router groups them by their longest
 * prefix of terminals (things that won't change such as /'s and words) and
 * then matches against those. When the router can't match against a route
 * in memory, it will simply scan the document tree until it can find a
 * controller. Okay, so, why go to all of the trouble of matching longest
 * prefixes? Routers ought to be simple! Well, this is still simple, but the
 * real benefit is that it means that two similar routes won't overwrite each
 * other. For example, the following won't conflict, even though from the
 * point of a regular expression they would:
 *
 * /controller/action/(:word)
 * /controller/action/foo
 * 
 * @author Peter Goodman
 */
class PinqRouteParser extends Dictionary implements Parser, ConfigurablePackage {
	
	// storage and other things
	protected $macro_keys = array(), // instead of constantly doing
			  $macro_vals = array(), // array_keys and array_values
			  $path = array();	 // information about the current path
	
	// so that it will fit nicely into CodeIgniter / Kohana
	protected $allowed_chars = "a-zA-Z0-9_-",
			  $arguments = array(), // arguments passed through the route
			  $directory, // the directory where the controller is
			  $partial_directory,
			  $controller, // the controller class to instantiate
			  $method, // the method of the controller to call
			  $default_controller = 'index',
			  $default_method = 'index';
	
	// some config stuff
	protected $base_dir,
			  $ext;
	
	/**
	 * Configure this package for the PackageLoader.
	 */
	static public function configure(Loader $loader, Loader $config, array $args) {
		
		extract($args);
		
		// require these array keys in the $args array
		PINQ_DEBUG && expect_array_keys($args, array(
			'controller_dir', 
			'file_extension', 
			'key'
		));
				
		// get the router. this could be this class or an extending class,
		// hence the new $class
		$router =  new $class($controller_dir, $file_extension);
		
		// load the router configuration file, and pass it the router as
		// context
		$config->load('package.route-parser', array('routes' => &$router));
		
		return $router;
	}

	/**
	 * Constructor, build up some default macros.
	 */
	final public function __construct($base_dir, $extension) {
		$this->addMacro('alpha',	'[a-zA-Z]+');
		$this->addMacro('num',		'[0-9]+');
		$this->addMacro('alphanum', '[a-zA-Z0-9]+');
		$this->addMacro('any',		'.*');
		$this->addMacro('word',		'\w+');
		$this->addMacro('year',		'[12][0-9]{3}');
		$this->addMacro('month',	'0[1-9]|1[012]');
		$this->addMacro('day',		'0[1-9]|[12][0-9]|3[01]');
		$this->addMacro('id',		'[0-9]+');
		$this->addMacro('uuid',		'[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]'.
									'{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}');
		
		$this->base_dir = $base_dir;
		$this->ext = $extension;
		
		$this->__init__(); // hook
	}

	/**
	 * Destructor.
	 */
	final public function __destruct() {
		$this->__del__(); // hook
	}
	
	/**
	 * Get the path info from the route.
	 */
	public function getPathInfo() {
		return array(
			$this->directory,
			$this->partial_directory,
			$this->controller,
			$this->method,
			$this->arguments,
		);
	}
	
	/**
	 * Add a macro that should be expanded into a regular expression.
	 */
	public function addMacro($id, $regex) {
		$this->macro_keys[] = '(:'. $id .')';
		$this->macro_vals[] = '('. $regex .')'; // force it to be a subpattern
	}
	
	/**
	 * Clean up the route a bit: take away useless spaces and /'s.
	 */
	protected function cleanPath($route) {
		return '/'. trim(preg_replace('~\s*[/]+\s*~', '/', $route), '/');
	}
	
	/**
	 * Get the longest prefix of terminals for a given route added through
	 * addRoute.
	 */
	protected function calculateLongestPrefix($route) {
		$matches = array();
		
		// get the longest prefix of terminals in this route
		$pattern = "~^(([". $this->allowed_chars ."]|/)+)~";
		if(preg_match($pattern, $route, $matches))
			return !empty($matches[0]) ? $matches[0] : '';
				
		return '';
	}
	
	/**
	 * Given all of the prefixes, find the longest matching one for a given
	 * route.
	 */
	protected function getLongestMatchingPrefix($route) {
		
		$parts = preg_split('~/~', $route, -1, PREG_SPLIT_NO_EMPTY);
		
		// we break the route up into segments
		if(0 == count($parts))
			return '';
		
		// go find the longest matching prefix
		$prefix = '';
		while(!empty($parts) && isset($this[$prefix .'/'. $parts[0]]))
			$prefix .= '/'. array_shift($parts);
				
		return $prefix;
	}
	
	/**
	 * Add a route for parsing. Remapping has two uses. First, we might be
	 * mapping a route to itself so that we can properly sanitize the
	 * incoming data, or we might be remapping a route, that is: creating a
	 * special way to access a controller's action without necessarily
	 * having a one-to-one relationship between the route and the path to the
	 * controller+action.
	 */
	public function addRoute($route, $maps_to) {
			
		// clean up the route and what it maps to
		$route = $this->cleanPath($route);
		$maps_to = $this->cleanPath($maps_to);
		
		// replace macros. Even though the normal macro can't be matched as
		// a terminal, it's possible that the macro is in fact a terminal,
		// even though this scenario seems redundant, we will replace macros
		// right now.
		$route = str_replace($this->macro_keys, $this->macro_vals, $route);
		
		// get the prefix of the route that is the sum of terminals
		$prefix = '/'. trim($this->calculateLongestPrefix($route), '/');
		
		// make sure we group all routes with the same prefix together, that
		// way when we encounter a route, we only search given its prefix
		if(!isset($this[$prefix]))
			$this->_dict[$prefix] = array();
		
		// add in the route to others with similar prefixes. we can also
		// disgard the prefix from the route as it is useless to us.
		$route = substr($route, strlen($prefix));
		$this->_dict[$prefix][] = array($route, $maps_to);
	}
	
	/**
	 * Parse a route into several segments. We make one central assumption:
	 * that the only routes with ordered arguments are ones that have been
	 * added in memory. Otherwise, we will just take anything after the 
	 * method (action) of the controller and consider it an argument.
	 */
	public function parse($route) {
		
		// clean up the incoming route. If we are at the 'root' of the
		// application then the route will end up as empty.
		$route = $path = trim($this->cleanPath($route), '/');
		
		// set up the defaults
		$this->controller = $this->default_controller;
		$this->method = $this->default_method;
		$this->directory = $this->base_dir;
		
		// route is empty, we're at the base controller and method
		if(empty($route))
			return $this->controllerFileExists();
		
		// calculate the prefix
		$prefix = $this->getLongestMatchingPrefix($route);
		
		// this will hold intermediate arguments
		$dynamic = array();
		
		// we're dealing with a route remapping so we want to find the route
		// we should actually be parsing. At the same time, we need to realize
		// that there might be dynamic elements in this incoming route that
		// need to be passed into the proper route mapping. We'll assume that
		// such dynamic elements are ordered correctly.
		if(isset($this[$prefix])) {
			
			// we don't want to mangle the actual route because we might not
			// find what we're looking for in here, so we'll work with a
			// temporary copy of the route
			$temp = substr($route, strlen($prefix)-1);
			$matches = array();
			
			// lets see if we have this route in memory, if not we will fall
			// through this if and assume that the route points directly to
			// a controller and an action.
			foreach($this[$prefix] as $suffix) {
				
				// remember, we store the route and what it maps to
				list($pattern, $maps_to) = $suffix;
				
				// does this route match any patterns? Note: a pattern must
				// match the routes from start to finish
				if(!preg_match('~^'.$pattern.'$~', $temp, $matches))
					continue;
				
				// make sure we get the arguments in the right order
				$this->reorderArguments($maps_to, $matches);
				$path = trim($maps_to, '/');
				break;
			}
		}
		
		// now that we (might) have collected the arguments, lets try to find
		// what controller it should belong to by searching through $path. The
		// extra /'s act as centinels to make sure that controller and method
		// will be found.
		$path_parts = explode('/', ltrim($path, '/') .'///');
		
		$i = -1;
		$base = $this->directory;
		$partial = '';
		
		// build up the directory to the controller, making sure to ignore
		// empty sub-directories
		while(isset($path_parts[++$i]) &&  is_dir($base .'/'. $path_parts[$i])) {
			$base .= '/'. $path_parts[$i];
			$partial .= '/'. $path_parts[$i];
		}
		
		$this->partial_directory = $partial;
		$this->directory = $base;

		// we might have found what we're looking for ;)
		if(!empty($path_parts[$i]))
			$this->controller = $path_parts[$i++];
		
		$first_arg = isset($this->arguments[0]) ? $this->arguments[0] : NULL;
		if(!empty($path_parts[$i]) && $path_parts[$i] != $first_arg)
			$this->method = $path_parts[$i];
		
		// does the controller file exist?
		return $this->controllerFileExists();
	}

	/**
	 * Does the file associated with the controller exist?
	 */
	function controllerFileExists() {
		return file_exists("{$this->directory}/{$this->controller}{$this->ext}");
	}
	
	/**
	 * It's possible that in the mapped route the arguments are in a different
	 * order than they are in the actual route, and thus need to be sent to
	 * the controller in a corrected order.
	 */
	protected function reorderArguments($route, array &$sub) {		
		  
		// make sure we end up with the right number of arguments. It doesn't
		// matter if we count too many, eg: $$1 counting as 2 instead of 1.
		$num_args = substr_count($route, '$');
		if($num_args > 0)
			$this->arguments = array_fill(0, $num_args, NULL);
		
		// find all the variables within the route (in order)
		$matches = array();
		if(!preg_match_all('~\$([0-9]+)~', $route, $matches))
			return;
		
		// the controller expects a certain number of arguments, we might not
		// actually get that many though, but that's no longer an issue
		$count = count($this->arguments);
		$i = -1;
		
		// iterate over the found variables
		while(isset($matches[1][++$i])) {
			
			// we will use this index to look into the $sub array for the
			// value of the ith argument.
			$index = (int)$matches[1][$i];
			
			// the route references an incorrect argument number, ignore it
			if(!isset($sub[$index]))
				continue;
			
			// put the argument in order.
			$this->arguments[$i] = $sub[$index];
		}
	}
	
	/**
	 * Convenient way to add a route.
	 */
	final public function offsetSet($route, $maps_to) {
		$this->addRoute($route, $maps_to);
	}

	/**
	 * Hooks.
	 */
	protected function __init__() { }
	protected function __del__() { }
}
