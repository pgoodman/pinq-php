<?php

/* $Id$ */

// everyone should code with these settings
error_reporting(E_ALL | E_STRICT);

// no need for this stuff
ini_set('register_argc_argv', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// hide php
header("X-Powered-By: Monkeys", TRUE); // mwahahahahaha
ini_set('expose_php', 0); // sometimes this does nothing

// get rid of this crap
ini_set('register_globals', 0);
ini_set('register_long_arrays', 0);
set_magic_quotes_runtime(0);

// make sure that in an infinite loop php shuts down
set_time_limit(120);
ignore_user_abort(FALSE);

// should some of the libraries in pinq operate in debug mode?
define('DEBUG_MODE', TRUE);

// are we in ISS?
define('PINQ_IN_ISS', defined('SERVER_IIS') && TRUE === SERVER_ISS);

// some core directories
define('DIR_SYSTEM', dirname(__FILE__));
define('DIR_PACKAGES', DIR_SYSTEM .'/packages/');

// core classes that stand on their own without pinq-defined interfaces/
// abstract classes
require_once DIR_SYSTEM .'/stack.php';
require_once DIR_SYSTEM .'/queue.php';
require_once DIR_SYSTEM .'/dictionary.php';

// interfaces and exceptions
require_once DIR_SYSTEM .'/interfaces.php';
require_once DIR_SYSTEM .'/exceptions.php';

// bring in the model stuff (not quite a "model layer")
require_once DIR_SYSTEM .'/model/__init__.php';

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
 * @author Peter GOodman.
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
			DIR_APPLICATION .'/controllers/',
			EXT,
		));

		// load the router configuration file, and pass it the router as 
		// context
		$config->load('routes', array(
			'routes' => &$router
		));

		// parse the URI, if it can't be parsed a 404 error will occur.
		if(!$router->parse(get_route()))
			set_http_status(404);
		
		// get the controller, method, and arguments form the route parser
		list($dir, $controller, $method, $arguments) = $router->getPathInfo();

		// bring in the controller file, we know it exists because the route
		// parser figured that out.
		require_once $dir .'/'. $controller . EXT;

		// get the class name and clean up the method name
		$class = class_name($controller) .'Controller';
		$method = function_name($method);

		// the following two functions will fail if the class doesn't exist,
		// so we will skip that test. make sure that the class is a Controller
		// and make sure that is has a method for the action we're trying to 
		// call.
		if(!is_subclass_of($class, 'Controller') || !method_exists($class, $method))
			set_http_status(404);

		// compress any output using zlib. this is done before the method call
		// as people might be using php's output functions instead of pinq's.
		if($config['config']['compress_output'])
			OutputBuffer::compress();

		try {
			// insantiate the controller an call its method
			$event = new $class($packages);
			$event->beforeAction(); // hook
			call_user_func_array(array($event, $method), $arguments);
			$event->afterAction(); // hook

		// other exceptions will be allowed to bubble up
		} catch(FlushBufferException $e) { }

		// flush the output buffer
		OutputBuffer::flush();

	// HTTP request errors, Thus is for allall http status codes >= 400.
	} catch(HttpRequestException $e) {

		print_r($e);

	// HTTP redirect exception, this is so that we adequately shut down
	// resources such as open database connections, etc.
	} catch(HttpRedirectException $e) {

		echo 'redirect';

	// catch ALL exceptions that have bubbled up this far. We hope there are 
	// none but there's no guarantee	
	} catch(Exception $e) {
		echo 'oh noes!';
		print_r($e);
	}

	// break references, we're done.
	unset($event, $config, $packages);
}
