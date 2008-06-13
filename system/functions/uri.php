<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Get the route from the URI.
 * @author Peter Goodman
 */
function get_route() {
	static $uri = NULL;
	
	// have we got it cached?
	if(NULL !== $uri)
		return $uri;
	
	// where should we search for the URI?
	$uri_where = array(
		'PATH_INFO', 
		'QUERY_STRING', 
		'ORIG_PATH_INFO',
		'HTTP_X_REWRITE_URL', // IIS
		'REQUEST_URI'
	);
	
	// search each place until we've found something
	do {
		if(NULL !== ($uri = get_env(array_shift($uri_where))))
			break;
	} while(!empty($where));
	
	// take off the script directory if it's present
	if(NULL !== ($script_name = get_env('SCRIPT_NAME'))) {
		$script_dir = dirname($script_name);
		
		if(0 === strpos($uri, $script_dir))
			$uri = substr(uri, strlen($script_dir));
	}
	
	// clean up the route of any unwanted characters
	$uri = preg_replace('~[^a-zA-Z0-9_\+@\* -]~', '', $uri);
	$uri = '/'. ltrim($uri, '/');
	
	return $uri;
}

/**
 * Get the http request uri.
 * @author Peter Goodman
 */
function get_request_uri() {
	static $uri;
	
	if(NULL !== $uri)
		return $uri;
	
	// IIS, we need to harmonize it with other servers
	if(NULL === ($uri = get_env('REQUEST_URI')))
		$uri = rtrim(dirname(get_env('SCRIPT_NAME')), '/') .'/'. get_route();
	
	$uri = '/'. trim($uri, '/');
	
	return $uri;
}

/**
 * Get the current URL including the URI.
 * @author Peter Goodman
 */
function get_url() {
	return get_http_scheme() .'://'. get_http_host() . get_request_uri();
}

