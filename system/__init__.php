<?php

/* $Id$ */

// get all the error levels we need
!defined('E_RECOVERABLE_ERROR') && define('E_RECOVERABLE_ERROR', 4096);
error_reporting(E_ALL | E_STRICT | E_RECOVERABLE_ERROR);

// time and locale settings
date_default_timezone_set('GMT');
setlocale(LC_CTYPE, 'C');

// hide the PHP version 
header("Server: Sparta", TRUE);
header("X-Powered-By: Spartans", TRUE);
ini_set('expose_php', 0);

// get rid of this stuff
ini_set('register_argc_argv', 0);
ini_set('register_globals', 0);
ini_set('register_long_arrays', 0);
set_magic_quotes_runtime(0);

// make sure that in an infinite loop php shuts down
set_time_limit(10);
ignore_user_abort(FALSE);

// should some of the libraries in pinq operate in debug mode?
define('PINQ_DEBUG', TRUE);
define('PINQ_IN_IIS', defined('SERVER_IIS') && TRUE === SERVER_IIS);
define('PINQ_MAX_META_RESPONSES', 2);

// some core directories
define('DIR_SYSTEM', dirname(__FILE__));
define('DIR_PACKAGES', DIR_SYSTEM .'/packages/');

// for when getting the raw POST data
define('RAW_POST_DATA', 'php://input');

// is php being dumb?
define('PHP_IS_BEING_DUMB', (bool)get_magic_quotes_gpc());

// some of the error controllers
define('ERROR_401', '/error/401');
define('ERROR_403', '/error/403');
define('ERROR_404', '/error/404');
define('ERROR_405', '/error/405');
define('ERROR_500', '/error/500');

// exceptions
require_once DIR_SYSTEM .'/exceptions.php';
require_once DIR_SYSTEM .'/interfaces.php';
require_once DIR_SYSTEM .'/response.php';

// core classes that stand on their own without pinq-defined interfaces/
// abstract classes
require_once DIR_SYSTEM .'/stack.php';
require_once DIR_SYSTEM .'/queue.php';
require_once DIR_SYSTEM .'/dictionary.php';
require_once DIR_SYSTEM .'/gateway.php';
require_once DIR_SYSTEM .'/type-handler.php';
require_once DIR_SYSTEM .'/loader.php';

// bring in the core stuff that's used everywhere
require_once DIR_SYSTEM .'/spl/__init__.php';
require_once DIR_SYSTEM .'/pql/__init__.php';
require_once DIR_SYSTEM .'/model/__init__.php';
require_once DIR_SYSTEM .'/data-source/__init__.php';

// stuff needed to get up and running
require_once DIR_SYSTEM .'/output-buffer.php';
require_once DIR_SYSTEM .'/config-loader.php';
require_once DIR_SYSTEM .'/package-loader.php';
require_once DIR_SYSTEM .'/resource.php';
require_once DIR_SYSTEM .'/functions/__init__.php';

/**
 * pinq(file name, relative directory) -> void
 *
 * This function taks a file name (eg: __FILE__) of where PINQ is being run 
 * from and a relative path from the dirname(file name) of where the
 * applications directory is. This function will set up and tear down the pinq
 * framework.
 * 
 * @note This function can only be run once
 * @author Peter Goodman.
 */
function pinq($script_file, $app_dir) {
	
	// fix the app dir
	$app_dir = realpath(dirname($script_file) .'/'. $app_dir) .'/';
	
	!defined('DIR_APPLICATION')
	 && define('EXT', '.'. pathinfo($script_file, PATHINFO_EXTENSION))
	 && define('PINQ_SCRIPT_FILENAME', $script_file)
	 && define('DIR_APPLICATION', $app_dir);
		
	try {
		// set things up!
		$config = new ConfigLoader;
		$config->load('config');

		// a pattern for what we expect the host to be
		!defined('PINQ_HTTP_HOST') && define(
			'PINQ_HTTP_HOST', 
			preg_quote($config['config']['host_name'])
		);
		
		// put restrictions on the use of the $GLOBALS array and the other 
		// super-globls arrays. The require is in here because it's possible 
		// that the call to get_host will throw an exception.
		require_once DIR_SYSTEM .'/globals.php';	

		// bring in the package loader
		$packages = new PackageLoader($config);
		$packages->load('input-dictionary');
		$router = $packages->load('route-parser', array(
			'controller_dir' => DIR_APPLICATION .'/resources/',
			'file_extension' => EXT,
		));
		
		// set up the GET and POST superglobals as read-only dictionaries
		$_POST = PinqInputDictionary::factory($_POST);
		$_GET = PinqInputDictionary::factory($_GET);

		// the starting route, taken from the url, it's outside of the
		// do-while loop because if any controllers yield to another
		// controller then the route to go to is passed in through the yield-
		// control exception and set in the catch() block.
		$route = Uri::getRoute();
		$request_method = get_request_method();

		// maintain a stack of controllers
		$events = new Stack;
		$event = $method = $output = NULL;
		
		ob_start();
		
		$i = 0;
		do {
			// look for a yield or a flush buffer
			try {
				
				// parse the URI, if it can't be parsed a 404 error will occur.
				if(!($path_info = $router->parse($route)))
					yield(ERROR_404);
				
				// get the controller, method, and arguments form the route
				// parser
				list($dir, $pdir, $controller, $action, $arguments) = $path_info;
				
				$type = Resource::getMediaGroup();
								
				// get the class name and clean up the method name
				$packages->load("resource.{$type}");
				$class = class_name("{$pdir} {$controller} resource {$type}");
				
				// bring in the controller file, we know it exists because the 
				// route parser figured that out.
				if(!class_exists($class, FALSE)) {
					
					if(!file_exists($dir .'/'. $controller . EXT))
						yield(ERROR_404);
				
					require_once $dir .'/'. $controller . EXT;
				
					// if we're not working with a valid controller then error
					if(!class_exists($class, FALSE) || 
					   !is_subclass_of($class, 'Resource'))
						yield(ERROR_404);
				}
				
				$file = "{$pdir}/{$controller}";
				
				// push a new resource onto the stack
				if($events->isEmpty())
					$events->push(new $class($packages, $file));
				
				// abort the previous event if it's different from this one
				else if(get_class($events->top()) != $class) {
					$events->top()->abort();
					$events->push(new $class($packages, $file));
				}
				
				// figure out what method to call and call it
				$event = $events->top();
				$method = $event->getMethod($request_method, $action);
				$event->beforeAction($method);
				$output = call_user_func_array(
					array($event, $method), 
					$arguments
				);
			
			// the controller has yielded its control to another controller
			} catch(MetaResponse $y) {
				
				$new_route = $y->getRoute();
				$new_request_method = $y->getRequestMethod();
				
				// if the inputs are the same then rethrow the exception to
				// avoid an infinite loop
				if($route == $new_route && $request_method == $new_request_method)
					throw $y;
				
				// change the next route that will be parsed and abort the
				// previous event
				$route = $new_route;
				$request_method = $new_request_method;
				
				continue;				
			}
			
			// all other exceptions will bubble up
			break;
			
		} while(++$i < PINQ_MAX_META_RESPONSES);
		
		$output .= ob_get_clean(); // TODO: replace with ob_end_clean() later on
		
	// HTTP redirect exception, this is so that we adequately shut down
	// resources such as open database connections, etc.
	} catch(HttpRedirectResponse $r) {		
		$redirect_url = $r->getLocation();
		if($event)
			$event->abort();

	// catch ALL exceptions that have bubbled up this far. We hope there are 
	// none but there's no guarantee.
	} catch(Exception $e) {
		echo $e->getMessage();
		echo '<pre>';
		print_r(array_slice($e->getTrace(), 0, 3));
		echo '</pre>';
	}
	
	// call all of the after-action hooks built up through yielding
	// and garbage collect the controllers. This is to simulate a
	// proper call stack.
	while(!$events->isEmpty()) {
		$event = $events->pop();
		$event->afterAction();
		unset($event);
	}
	
	// break references, we're done.
	unset(
		$events, 
		$router,
		$_SESSION, $_POST, $_GET, 
		$config, 
		$packages
	);
	
	// redirect if necessary
	if(isset($redirect_url) && !headers_sent()) {
		header("Location: {$redirect_url}", TRUE, 303);
		exit;
	}
	
	echo (string)$output;
}