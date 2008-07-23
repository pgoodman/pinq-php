<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * __sort_routes(array $a, array $b) -> int
 *
 * Sort a route into descending order of pattern length.
 *
 * @author Peter Goodman
 */
function __sort_routes(array $a, array $b) {
	return strlen($a[0]) > strlen($b[0]) ? -1 : 1;
}

/**
 * Class that collects and parses routes. Accepts programmer defined route
 * remappings. The router groups these remappings by their longest prefix of 
 * terminals (things that won't change such as /'s and words) and then matches 
 * the URI against those. When the router cannot match the URI against a route
 * in memory, it will simply scan the document tree until it can find a
 * controller. 
 *
 * This router is designed such  that two similar routes won't overwrite each-
 * other. 
 *
 * @example
 *     The following two routes will not conflict, even though from the point
 *     of view of a regular expression they would:
 *         /controller/action/(:word)
 *         /controller/action/foo
 *
 *     When working with the index controller of a project, it can often be
 *     disirable to have methods other than index that are available. However,
 *     methods other than *_index of an application index controller are only
 *     accessible directly, as show:
 *         /                  -> IndexResource::ANY
 *         /index/method      -> IndexResource::ANY_method
 *
 *     To be able to access /index/method instead as /method, one would add
 *     the following route re-mapping in the /application/config/package.route
 *     -parser.php configuration file:
 *         $routes['/method'] = '/index/method';
 * 
 * @author Peter Goodman
 */
class PinqRouteParser extends Dictionary implements Parser, ConfigurablePackage {
	
	// storage and other things
	protected $macro_keys = array(), // instead of constantly doing
			  $macro_vals = array(), // array_keys and array_values
			  $path = array();	 // information about the current path
	
	protected $allowed_chars = "a-zA-Z0-9_+-",
			  $default_resource = 'index',
			  $default_method = '';
	
	// some config stuff
	protected $base_dir,
			  $ext;
	
	/**
	 * PinqRouteParser::configure(PackageLoader, ConfigLoader, array $args) 
	 * -> {Package, void}
	 *
	 * Configure this package for the PackageLoader and return a new instance
	 * of the route parser.
	 *
	 * @note When extending this class, there is no need to change this method
	 *       as the class name to be instantiated is passed in.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {
		extract($args);
		
		if(!isset($resources_dir)) {
			throw new DomainException(
				"PinqRouteParser::configure() expects first parameter to be ".
				"directory. None given."
			);
		}
		
		// get the router. this could be this class or an extending class,
		// hence the new $class
		$router = new $class($resources_dir);
		
		// load the router configuration file, and pass it the router as
		// context
		$config->load('package.route-parser', array(
			'routes' => &$router
		));
		
		return $router;
	}

	/**
	 * PinqRouteParser(string $base_dir, string $php_file_extension)
	 */
	final public function __construct($base_dir) {
		$this->addMacro('alpha',	'[a-zA-Z]+');
		$this->addMacro('num',		'[0-9]+');
		$this->addMacro('alphanum', '[a-zA-Z0-9]+');
		$this->addMacro('any',		'.*');
		$this->addMacro('word',		'\w+');
		$this->addMacro('year',		'[12][0-9]{3}');
		$this->addMacro('month',	'0[1-9]|1[012]');
		$this->addMacro('day',		'0[1-9]|[12][0-9]|3[01]');
		$this->addMacro('uuid',		'[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]'.
									'{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}');
		$this->addMacro('hex',      '[a-fA-F0-9]+');
		
		$this->base_dir = $base_dir;
		
		$this->__init__(); // hook
	}

	/**
	 */
	final public function __destruct() {
		$this->__del__(); // hook
	}
	
	/**
	 * $r->defaultPathInfo(void) -> array
	 *
	 * Get the default path info. This, similar to parse(), returns an array
	 * structured as such:
     *
	 * 1) absolute path to controller file's containing directory
	 * 2) path to controller file's contraining directory starting from the
	 *    root of the controller's directory.
	 * 3) the controller file name, formatted throuhg function_name()
	 * 4) the action name, formatted through function_name()
	 * 5) an array of arguments to pass to the controller action
	 *
	 * @see function_name(...)
	 * @see PinqRouteParser::parse(...)
	 */
	protected function defaultPathInfo() {
		return array(
			$this->base_dir .'/',
			'/',
			$this->default_resource,
			$this->default_method,
			array()
		);
	}
	
	/**
	 * $r->addMacro(string $id, string $regex) -> void
	 *
	 * Add a macro that should be expanded into a regular expression.
	 */
	public function addMacro($id, $regex) {
		$this->macro_keys[] = '(:'. $id .')';
		$this->macro_vals[] = '('. $regex .')'; // force it to be a subpattern
	}
	
	/**
	 * $r->cleanPath(string $route) -> string
	 *
	 * Clean up the route a bit: take away useless spaces and /'s.
	 */
	protected function cleanPath($route) {
		return '/'. trim(preg_replace('~\s*[/]+\s*~', '/', $route), '/');
	}
	
	/**
	 * $r->calculateLongestPrefix(string $route) -> string
	 *
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
	 * $r->getLongestMatchingPrefix(string $route) -> string
	 *
	 * Incrementally check each route part of $route against the existing
	 * route prefixes and return the longest one.
	 */
	protected function getLongestMatchingPrefix($route) {
		
		$parts = preg_split('~/~', $route, -1, PREG_SPLIT_NO_EMPTY);
				
		// go find the longest matching prefix
		$prefix = '';
		while(!empty($parts) && isset($this[$prefix .'/'. $parts[0]]))
			$prefix .= '/'. array_shift($parts);
		
		if($prefix == '')
			$prefix = '/';
		
		return $prefix;
	}
	
	/**
	 * $r->addRoute(string $route, string $maps_to) -> void
	 *
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
	 * $r->parse(string $route) -> array
	 *
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
		$controller = $this->default_resource;
		$method = $this->default_method;
		$directory = $this->base_dir;
		$partial_directory = '/';
		$arguments = array();
		
		// route is empty, we're at the base controller and method
		if(empty($route))
			return $this->defaultPathInfo();
		
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
			$routes = &$this->_dict[$prefix];
			$matches = array();
						
			// sort the routes in descending order of pattern length to 
			// hopefully maximize our changes of matching the right route
			// early
			usort($routes, '__sort_routes');
			
			// lets see if we have this route in memory, if not we will fall
			// through this if and assume that the route points directly to
			// a controller and an action.
			foreach($routes as $suffix) {
				
				// remember, we store the route and what it maps to
				list($pattern, $maps_to) = $suffix;
				
				// does this route match any patterns? Note: a pattern must
				// match the routes from start to finish
				if(!preg_match('~^'.$pattern.'$~', $temp, $matches))
					continue;
								
				// make sure we get the arguments in the right order
				$arguments = $this->parseArguments($maps_to, $matches);
				$path = $maps_to;
				break;
			}
		}
		
		// now that we (might) have collected the arguments, lets try to find
		// what controller it should belong to by searching through $path. The
		// extra /'s act as centinels to make sure that controller and method
		// will be found.
		$path_parts = explode('/', ltrim($path, '/') .'///');
		$i = 0;
		
		// build up the directory to the controller, making sure to ignore
		// empty sub-directories
		while(isset($path_parts[$i]) && is_dir($directory .'/'. $path_parts[$i])) {
			$directory .= '/'. $path_parts[$i];
			$partial_directory .= '/'. $path_parts[$i++];
		}

		// we might have found what we're looking for ;)
		if(!empty($path_parts[$i]))
			$controller = $path_parts[$i++];
		
		// make sure that we can find the method and that what we expect to
		// be the method is not the first argument. If it is the first argument
		// then we keep with the default_method
		$first_arg = isset($arguments[0]) ? $arguments[0] : NULL;
		if(!empty($path_parts[$i]) && $path_parts[$i] != $first_arg)
			$method = $path_parts[$i++];
		
		if(empty($arguments))
			$arguments = array_slice($path_parts, $i);
		
		// make it applicable as a file name
		$controller = function_name($controller);
		$method = function_name($method);
		
		// does the controller file exist?
		if(!file_exists("{$directory}/{$controller}.php"))
			return FALSE;
		
		// return path info
		return array(
			$directory,
			$partial_directory,
			$controller,
			$method,
			$arguments,
		);
	}
	
	/**
	 * $r->parseArguments(string $route, array $sub) -> array
	 *
	 * It's possible that in the mapped route the arguments are in a different
	 * order than they are in the actual route, and thus need to be sent to
	 * the controller in a corrected order. Figure out the corrected order of
	 * the arguments and return them.
	 *
	 * @note This method does not do argument concatenation
	 */
	protected function parseArguments(&$route, array $sub) {
		
		// find all the variables within the route (in order)
		$matches = array();
		if(!preg_match_all('~\$([0-9]+)~', $route, $matches))
			return array();
		
		// the controller expects a certain number of arguments, we might not
		// actually get that many though, but that's no longer an issue
		$count = count(array_unique($matches[1]));
		$i = -1;
		
		// fill up the arguments array
		$arguments = array_fill(0, $count, NULL);
		$subs = array();
		
		// iterate over the found variables
		while(isset($matches[1][++$i])) {
			
			// we will use this index to look into the $sub array for the
			// value of the ith argument.
			$index = (int)$matches[1][$i];
			
			// the route references an incorrect argument number, ignore it
			if(!isset($sub[$index]))
				continue;
			
			// put the argument in order.
			$arguments[$i] = $sub[$index];
			$subs[$i] = "\${$index}";
		}
		
		// put the arguments into the route, in their proper order
		$route = str_replace($subs, $arguments, $route);
		
		return $arguments;
	}
	
	/**
	 * $r->offsetSet(...) <==> $r->addRoute(...) <==> $r[$route] = $maps_to
	 *
	 * Convenient way to add a route, especially from config files.
	 */
	final public function offsetSet($route, $maps_to) {
		$this->addRoute($route, $maps_to);
	}

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
