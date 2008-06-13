<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Get an environment variable. As is obvious, parts of this function are
 * taken from the CakePHP coding project. Thanks guys!
 * Most of this function: Copyright 2005-2008, Cake Software Foundation, Inc.
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
	
	// do we need to double-check stuff?
	if($var == 'REMOTE_ADDR') {
		
		// they might be behind a proxy
		if(NULL !== ($addr = get_env('HTTP_X_FORWARDED_FOR')))
			$val = $addr;
		
		// no too sur
		else if (NULL !== ($addr = get_env('HTTP_PC_REMOTE_ADDR')))
			$val = $addr;
	
	// if we're behind a proxy then there might be an issue
	} else if($var == 'SERVER_ADDR') {
		
		//if(NULL !== ($addr = get_env('HTTP_X_REAL_IP')))
		//	$val = $addr;
		
	} else if($var == 'PHP_SELF') {
		// php bug #42523
		if(PINQ_IN_ISS && version_compare(PHP_VERSION, '5.2.4') <= 0) {
			$len = strlen($val);
			if(substr($val, 0, $len) == substr($val, $len))
				return substr($val, 0, $len);
		}
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
	if(PINQ_IN_ISS)
		return str_replace('\\\\', '\\', get_env('PATH_TRANSLATED'));
	
	return get_env('SCRIPT_FILENAME');
}
