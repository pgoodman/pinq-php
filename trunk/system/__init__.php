<?php

/* $Id$ */

// get all the error levels we need
!defined('E_RECOVERABLE_ERROR') && define('E_RECOVERABLE_ERROR', 4096);
error_reporting(E_ALL | E_STRICT | E_RECOVERABLE_ERROR);

// time and locale settings
date_default_timezone_set('GMT');
setlocale(LC_CTYPE, 'C');

// no need for this stuff
ini_set('register_argc_argv', 0);

// hide php
header("Server: Sparta", TRUE);
header("X-Powered-By: Spartans", TRUE); // mwahahahahaha
ini_set('expose_php', 0); // sometimes this does nothing, hence the above

// get rid of this crap
ini_set('register_globals', 0);
ini_set('register_long_arrays', 0);
set_magic_quotes_runtime(0);

// make sure that in an infinite loop php shuts down
set_time_limit(120);
ignore_user_abort(FALSE);

// should some of the libraries in pinq operate in debug mode?
define('PINQ_DEBUG', TRUE);

// are we in IIS?
define('PINQ_IN_IIS', defined('SERVER_IIS') && TRUE === SERVER_IIS);

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
require_once DIR_SYSTEM .'/general-exceptions.php';
require_once DIR_SYSTEM .'/control-flow-exceptions.php';

// core classes that stand on their own without pinq-defined interfaces/
// abstract classes
require_once DIR_SYSTEM .'/stack.php';
require_once DIR_SYSTEM .'/queue.php';
require_once DIR_SYSTEM .'/dictionary.php';

// interfaces and abstract classes
require_once DIR_SYSTEM .'/loader.php';
require_once DIR_SYSTEM .'/interfaces.php';

// bring in the core stuff that's used everywhere
require_once DIR_SYSTEM .'/spl/__init__.php';
require_once DIR_SYSTEM .'/pql/__init__.php';
require_once DIR_SYSTEM .'/model/__init__.php';
require_once DIR_SYSTEM .'/data-source/__init__.php';

// stuff needed to get up and running
require_once DIR_SYSTEM .'/output-buffer.php';
require_once DIR_SYSTEM .'/config-loader.php';
require_once DIR_SYSTEM .'/package-loader.php';
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
	
	// by these constant definitions we can only call this function once
	define('EXT', '.'. pathinfo($script_file, PATHINFO_EXTENSION));
	define('PINQ_SCRIPT_FILENAME', $script_file);
	define('DIR_APPLICATION', $app_dir);
		
	try {

		yield(get_route(), get_request_method());
		
		// flush the output buffer
		OutputBuffer::flush('out');
		
	// HTTP redirect exception, this is so that we adequately shut down
	// resources such as open database connections, etc.
	} catch(HttpRedirectException $r) {
		
		set_http_status(303);
		
		echo 'redirect: '. $r->getLocation();

	// catch ALL exceptions that have bubbled up this far. We hope there are 
	// none but there's no guarantee.
	} catch(Exception $e) {
		echo $e->getMessage();
	}

	// break references, we're done.
	unset($_SESSION, $config, $packages);
}

/**
 * yield(string $route[, string $request_method])
 *
 * Yield control to another controller's action by passing in a route to that
 * controller's action. This function works by throwing a new YieldControllerException
 * exception.
 *
 * @note The action being called will be called using the same request method
 *       as the current action.
 * @author Peter Goodman
 */
function yield($route, $request_method = NULL) {
	
	static $config,
	       $packages,
	       $layout_view,
	       $router,
	       $events,
	       $scope_stack;
	
	// set up the default stuff
	if(NULL === $config) {
		
		// set things up!
		$config = new ConfigLoader;
		$config->load('config');

		// a pattern for what we expect the host to be
		define('PINQ_HTTP_HOST', preg_quote($config['config']['host_name']));

		// put restrictions on the use of the $GLOBALS array and the other 
		// super-globls arrays. The require is in here because it's possible 
		// that the call to get_host will throw an exception.
		require_once DIR_SYSTEM .'/globals.php';	

		// bring in the package loader
		$packages = new PackageLoader($config);

		// bring a page controller class. the page controller doesn't actually 
		// install itself into the packages dictionary
		$packages->load('controller');

		// bring in and configure the route parser with a context.
		$router = $packages->load('route-parser', array(
			'controller_dir' => DIR_APPLICATION .'/controllers/',
			'file_extension' => EXT,
		));
	
		// set some of the global variables
		$packages->load('input-dictionary');
		$_POST = PinqInputDictionary::factory($_POST);
		$_GET = PinqInputDictionary::factory($_GET);
	
		// create the page and layout views
		$packages->load('view');
		$layout_view = PinqView::factory();
		$layout_view['page_view'] = PinqView::factory();
	
		// maintain a stack of controllers
		$events = new Stack;
		$scope_stack = $packages->load('scope-stack');
	}
	
	// get the request method
	if(NULL === $request_method)
		$request_method = get_request_method();
	
	// should the view be rendered?
	$render_layout = FALSE;
	
	$return = NULL;
	$e = NULL;
	
	//do {
		// look for a yield or a flush buffer
		try {
		
			// parse the URI, if it can't be parsed a 404 error will occur.
			if(!($path_info = $router->parse($route)))
				yield(ERROR_404);
			
			// get the controller, method, and arguments form the route
			// parser
			list($dir, $pdir, $controller, $method, $arguments) = $path_info;
			
			// bring in the controller file, we know it exists because the 
			// route parser figured that out.
			if(!file_exists($dir .'/'. $controller . EXT))
				yield(ERROR_404);
			
			require_once $dir .'/'. $controller . EXT;

			// get the class name and clean up the method name
			$class = class_name("{$pdir} {$controller} controller");
			
			// if we're not working with a valid controller then error
			if(!is_subclass_of($class, 'PinqController'))
				yield(ERROR_404);
			
			// only instantiate a new controller if we're yielding to a
			// different controller
			//if($events->isEmpty() || get_class($events->top()) != $class) {
			$event = new $class(
				$packages, 
				$layout_view, 
				$layout_view['page_view']
			);
			$events->push($event);
			$event->beforeAction();
			
			//} else
			//	$event = $events->top();
			
			// call the event's action
			$event->act(
				$request_method, 
				$method, 
				$arguments, 
				"{$pdir}/{$controller}/"
			);
			
			// get some view info before we finish with the controller
			// stuff
			$render_layout = (bool)$event->render_layout;
		
		} catch(Exception $e) {
			
			$event = $events->silentPop();
			$event->afterAction();
			unset($event);
			
			// return to the yielding controller
			if($e instanceof ResumeControllerException) {
				
				// return from here if there is something on the even stack. if
				// there is nothing on the event stack then we will return
				// after we've released up all resources.
				if(!$events->isEmpty())
					return $e->getArgs();
				
				$return = $e->getArgs();
			
			// we want the current event to be a leaf event
			} else if($e instanceof StopControllerException) {
				if(!$events->isEmpty())
					throw $e;			
			}
			
			// other exceptions are let through so that we can clean up
			// resources before re-throwing
		}
		
		// execute all of the after action hooks
		while(!$events->isEmpty()) {
			$event = $events->pop();
			$event->afterAction();
			unset($event);
		}
		
		// render the layout
		if(!$e && $render_layout) {
			$layout_view->render(
				$scope_stack
			);
		}
		
		// clear up resources
		unset(
			$event, $events, $scope_stack, $layout_view, 
			$route, $config, $packages
		);
		
		// an exception was thrown that we couldn't identify, clear up all
		// resources then rethrown
		if($e instanceof Exception)
			throw $e;
		
		return $return;
		
		// the controller has yielded its control to another controller
		/*} catch(YieldControllerException $y) {
			
			// clear the output buffer for the new action
			OutputBuffer::clearAll();
			
			// put the message from the exception into the new output
			// buffer. if there was no message, ie: this was not cause by
			// an error that threw an exception
			err($y->getMessage());
			
			// don't want infinite loop!
			if($route === ($new_route = $y->getRoute()))
				throw $y;
			
			// change the next route that will be parsed
			$route = $new_route;
			$request_method = $y->getRequestMethod();
			
			continue;
			
		// output and stop, we catch this early 
		} catch(FlushBufferException $e) { }*/
		
		// call all of the acter action hooks built up through yielding
		// and garbage collect the controllers. This is to simulate a
		// proper call stack.
		/*while(!$events->isEmpty()) {
			$event = $events->pop();
			$event->afterAction();
			unset($event);
		}
		unset($events);
		
		// not rendering a layout, leave
		if(!$render_layout)
			break;
		
		// render and output the layout view
		$layout_view->render(
			$scope_stack
		);
		
		// layout view no longer needed
		unset($layout_view);
		
		// all other exceptions will bubble up
		break;*/
		
	//} while(TRUE);
}

function stop() {
	throw new StopControllerException;
}

function resume() {
	$args = func_get_args();
	throw new ResumeControllerException($args);
}
