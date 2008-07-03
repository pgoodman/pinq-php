<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * get_route(void) -> string
 *
 * Get the current route from the URI.
 *
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
	$uri = preg_replace('~[^a-zA-Z0-9_\+@\* -/]~', '', $uri);
	$uri = '/'. ltrim($uri, '/');
	
	return $uri;
}

/**
 * get_uri(void) -> string
 *
 * Get the http request uri. This is everything but the scheme, user/pass,
 * host, and port. Meaning: this is any directory/route path and file name.
 *
 * @author Peter Goodman
 */
function get_uri() {
	static $uri;
	
	if(NULL !== $uri)
		return $uri;
	
	// IIS, we need to harmonize it with other servers
	// TODO: do GET variables need to be added back in?
	if(NULL === ($uri = get_env('REQUEST_URI')))
		$uri = rtrim(dirname(get_env('SCRIPT_NAME')), '/') .'/'. get_route();
	
	$uri = '/'. trim($uri, '/');
	
	return $uri;
}

/**
 * get_url(void) -> string
 *
 * Get the current URL.
 *
 * @author Peter Goodman
 */
function get_url() {
	return get_http_scheme() .'://'. get_http_host() . get_uri();
}

/**
 * get_base_url(void) -> string
 *
 * Get the base URL, sans the route.
 *
 * @author Peter Goodman
 */
function get_base_url() {
	static $url;
	
	if(NULL !== $url)
		return $url;
	
	// remove any query parameters and anchors from the end of the url
	$url = preg_replace('~(\?|#).*$~', '', get_url());
	
	// remove the route from the end of the url
	$url = preg_replace('~'. preg_quote(get_route()) .'$~', '', $url);
	
	return $url;
}

/**
 * url([string $part1[, string $part2[, ...]]]) -> string
 *
 * Rebuild the current url with a specific uri. Each argument to this function
 * is a path part of the uri.
 *
 * @author Peter Goodman
 */
function url() {
	$route_parts = func_get_args();
	return get_base_url() .'/'. implode('/', $route_parts);
}
