<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * env(string $var) -> string
 *
 * Return an environment variable. This function looks in several places: the
 * _SERVER and _ENV superglobals, then it uses getenv() and if the server is
 * Apache it will look in Apache's environment variables as well.
 *
 * @author Peter Goodman
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
 * set_env(string $var, string $val) -> void
 *
 * Set an environment variable. If it exists somewhere then it will be
 * overwritten.
 *
 * @author Peter Goodman
 */
function set_env($var, $val = NULL) {
	
	$var = (string)$var;
	$val = (string)$val;
	
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
 * get_document_root(void) -> string
 *
 * Get the current document root.
 *
 * @copyright Copyright 2005-2008, Cake Software Foundation, Inc.
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
 * get_script_filename(void) -> string
 *
 * Get the script filename.
 *
 * @author Peter Goodman
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
 * get_user_ip(void) -> string
 *
 * Attempt to get the user's IP address.
 *
 * @author Peter Goodman, CakePHP, CodeIgniter
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
	
	// match the ip address against an IPv4 pattern matcher
	if(!preg_match('~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$~', $ip))
		$ip = '0.0.0.0';
	
	return $ip;
}
