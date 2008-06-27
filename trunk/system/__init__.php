<?php

/* $Id$ */

// everyone should code with these settings
error_reporting(E_ALL | E_STRICT);

// no need for this stuff
ini_set('register_argc_argv', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

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
define('DEBUG_MODE', TRUE);

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
define('ERROR_403', '/error/403');
define('ERROR_404', '/error/404');
define('ERROR_405', '/error/405');
define('ERROR_500', '/error/500');

// start the session and regenerate the id
session_start();
//session_regenerate_id(FALSE);

// core classes that stand on their own without pinq-defined interfaces/
// abstract classes
require_once DIR_SYSTEM .'/stack.php';
require_once DIR_SYSTEM .'/queue.php';
require_once DIR_SYSTEM .'/dictionary.php';

// interfaces and abstract classes
require_once DIR_SYSTEM .'/loader.php';
require_once DIR_SYSTEM .'/interfaces.php';

// exceptions
require_once DIR_SYSTEM .'/general-exceptions.php';
require_once DIR_SYSTEM .'/control-flow-exceptions.php';

// bring in the model stuff
require_once DIR_SYSTEM .'/pql/__init__.php';
require_once DIR_SYSTEM .'/model/__init__.php';
require_once DIR_SYSTEM .'/data-source/__init__.php';

// stuff needed to get up and running
require_once DIR_SYSTEM .'/output-buffer.php';
require_once DIR_SYSTEM .'/config-loader.php';
require_once DIR_SYSTEM .'/package-loader.php';
require_once DIR_SYSTEM .'/functions/__init__.php';

/**
 * Run PINQ! This function sets up and tears down everything. It's simple to
 * follow so just read the comments to get an idea of how a pinq application
 * progresses from a request to output to teardown. Note: this function can
 * only be called once.
 * @param $script_file ALWAYS USE __FILE__!!!!!
 * @param $app_dir The directory of the applications folder RELATIVE to the
 *                 script file.
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

		// set things up!
		$config = new ConfigLoader;
		$config->load('config');
		$config->load('config-defaults');

		// make sure that all default required configuration settings exist
		$config['config'] = array_merge(
			$config['config-defaults'], 
			$config['config']
		);

		// a pattern for what we expect the host to be
		define('PINQ_HTTP_HOST', preg_quote($config['config']['host_name']));

		// we no longer need these
		unset($config['config-defaults']);

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
		
		// the starting route, taken from the url, it's outside of the
		// do-while loop because if any controllers yield to another
		// controller then the route to go to is passed in through the yield-
		// control exception and set in the catch() block.
		$route = get_route();
		
		do {
			
			// look for a yield or a flush buffer
			try {
			
				// parse the URI, if it can't be parsed a 404 error will occur.
				if(!$router->parse($route))
					yield(ERROR_404);
			
				// get the controller, method, and arguments form the route
				// parser
				$path_info = $router->getPathInfo();
				list($dir, $pdir, $controller, $method, $arguments) = $path_info;
			
				// bring in the controller file, we know it exists because the 
				// route parser figured that out.
				require_once $dir .'/'. $controller . EXT;

				// get the class name and clean up the method name
				$class = class_name($controller) .'Controller';
				$method = function_name($method);
				$request_method = $_SERVER['REQUEST_METHOD'];

				// if we're not working with a valid controller then error
				if(!is_subclass_of($class, 'PinqController'))
					yield(ERROR_404);
				
				// create the page and layout views
				$packages->load('view');
				$layout_view = View::factory();
				$layout_view['page_view'] = page_view(
					"{$pdir}/{$controller}/{$method}"
				);
				
				// insantiate the controller and call its action
				$event = new $class(
					$packages, 
					$layout_view, 
					$layout_view['page_view']
				);
				
				$event->act($request_method, $method, $arguments);
				
				// get some view info before we finish with the controller
				// stuff
				$render_layout = (bool)$event->render_layout;
				
				// clear the controller, it's no longer needed
				unset($event);
				
				// not rendering a layout, leave
				if(!$render_layout)
					break;
				
				// render and output the layout view
				$layout_view->render(new StackOfDictionaries);
				
				// layout view no longer needed
				unset($layout_view);
			
			// the controller has yielded its control to another controller
			} catch(YieldControlException $y) {
				
				// get rid of the previous controller
				// TODO: getting rid of the controller is useful, but should
				//       the afterAction() hook be called?
				if(isset($event)) unset($event);
				
				// clear the output buffer for the new action
				// TODO: does it make sense to clear the output buffer or
				//       not? Someone else decide!!!
				OutputBuffer::clear();
				
				// don't want infinite loop!
				if($route === ($new_route = $y->getRoute()))
					break;
				
				// change the next route that will be parsed
				$route = $new_route;
				continue;
				
			// output and stop, we catch this early 
			} catch(FlushBufferException $e) { }
			
			// all other exceptions will bubble up
			break;
			
		} while(TRUE);
		
		// compress any output using zlib. this is done before the method
		// call as people might be using php's output functions instead of
		//  pinq's.
		if($config['config']['compress_output'])
			OutputBuffer::compress();
		
		// flush the output buffer
		OutputBuffer::flush();
		
	// HTTP redirect exception, this is so that we adequately shut down
	// resources such as open database connections, etc.
	} catch(HttpRedirectException $r) {
		
		set_http_status(303);
		
		echo 'redirect: '. $r->getLocation();

	// catch ALL exceptions that have bubbled up this far. We hope there are 
	// none but there's no guarantee.
	} catch(Exception $e) {
		print_r($e);
		echo '<br>top-level exception';
		
	}

	// break references, we're done.
	unset($config, $packages);
}
