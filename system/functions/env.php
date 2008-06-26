<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Get an environment variable.
 */
function get_env($var) {
	
	$val = NULL;
	
	// look in the usual places
	if(isset($_SERVER[$var]))
		$val = $_SERVER[$var];
	
	// normal places for env variables
	else if(isset($_ENV[$var]))
		$val = $_ENV[$var];	
	
	else if(FALSE !== ($ret = getenv($var)))
		$val = $ret;
	
	// apache-specific environment function
	else if(function_exists('apache_getenv')) {
		if(FALSE !== ($ret = apache_getenv($var)))
			$val = $ret;
	}
	
	return $val;
}

/**
 * Set an environment variable. If it exists somewhere then it will be
 * overwritten at that point.
 */
function set_env($var, $val = NULL) {
	
	// look in the usual places
	if(isset($_SERVER[$var]))
		$_SERVER[$var] = $val;
	
	// apache-specific environment function
	if(function_exists('apache_getenv')) {
		if(FALSE !== apache_getenv($var))
			apache_setenv($var, $val);
	}
	
	// just do a normal set where we would *expect* the environment variable
	// to be. this is so that future calls to get_env() get it even if the
	// value is different through PHP's getenv().
	$_ENV[$var] = $val;
}

/**
 * The contents of this function copied verbatim (then modified for length) 
 * from CakePHP. Why isn't this in the get_env function? Check globals.php!
 * Most of this function: Copyright 2005-2008, Cake Software Foundation, Inc.
 */
function get_document_root() {
	
	if(NULL !== ($doc_root = get_env('DOCUMENT_ROOT')))
		return $doc_root;
	
	$offset = 0;
	$script_filename = get_env('SCRIPT_FILENAME');
	$script_name = get_env('SCRIPT_NAME');
	
	if (!strpos($script_name, '.php'))
		$offset = 4;
	
	$cut = strlen($script_filename) - (strlen($script_name) + $offset);
	
	return substr($script_filename, 0, $cut);
}

/**
 * Get the script filename.
 */
function get_script_filename() {
	
	// this is defined in the index.php that serves pages
	if(defined('PINQ_SCRIPT_FILENAME'))
		return PINQ_SCRIPT_FILENAME;
	
	// cakephp ticket #2683, this could be problematic as php5 on apache no
	// longer uses PATH_TRANSLATED
	if(PINQ_IN_IIS)
		return str_replace('\\\\', '\\', get_env('PATH_TRANSLATED'));
	
	return get_env('SCRIPT_FILENAME');
}

/**
 * Get the user's IP. Parts of this function are inspired by the CakePHP and
 * CodeIgniter PHP libraries.
 */
function get_user_ip() {
	
	static $ip;
	
	if($ip !== NULL)
		return $ip;
	
	$ip_places = array(
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_PC_REMOTE_ADDR',
	);
	
	// go through the different ip places and try to find the ip
	while(!empty($ip_places)) {
		$addr = get_env(array_shift($ip_places));
	
		if(!empty($addr))
			$ip = $addr;
	}
	
	// thanks CI for this little nugget
	if(FALSE !== strpos($ip, ',')) {
		$ip = end(explode(',', $ip));
	}
	
	if(!preg_match('~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$~', $ip))
		$ip = '0.0.0.0';
	
	return $ip;
}

/**
 * Get the current request method, If it doesn't validate then default to GET.
 */
function get_request_method() {
	static $request_method;
	
	if(NULL !== $request_method)
		return $request_method;
	
	$methods = array(
		'GET', 'HEAD',
		'PUT', 'POST', 'DELETE',
		'OPTIONS', 'TRACE',
	);
	
	// default to a GET request
	$method = strtoupper(get_env('REQUEST_METHOD'));
	if(!in_array($method, $methods))
		$method = 'GET';
	
	return $request_method = strtoupper($method);
}
