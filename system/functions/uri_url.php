<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class that handles URI (uniform resource identifier) things.
 *
 * @author Peter Goodman
 */
class Uri {
	
	/**
	 * Uri::getRoute(void) -> string
	 *
	 * Get the current route from the URI.
	 *
	 * @author Peter Goodman
	 */
	static public function getRoute() {
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
	 * Uri::get(void) -> string
	 *
	 * Get the http request uri. This is everything but the scheme, user/pass,
	 * host, and port. Meaning: this is any directory/route path and file name.
	 *
	 * @author Peter Goodman
	 */
	static public function get() {
		static $uri;

		if(NULL !== $uri)
			return $uri;

		// IIS, we need to harmonize it with other servers
		// TODO: do GET variables need to be added back in?
		if(NULL === ($uri = get_env('REQUEST_URI')))
			$uri = rtrim(dirname(get_env('SCRIPT_NAME')), '/') .'/'. Uri::getRoute();

		$uri = '/'. trim($uri, '/');

		return $uri;
	}
}

/**
 * Class that handles URL (uniform resource locator) things.
 *
 * @author Peter Goodman
 */
class Url {
	
	/**
	 * Url::get(void) -> string
	 *
	 * Get the current URL.
	 *
	 * @author Peter Goodman
	 */
	static public function get() {
		return Http::getScheme() .'://'. Http::getHost() . Uri::get();
	}
	
	/**
	 * Url::getBase(void) -> string
	 *
	 * Get the base URL, sans the route.
	 *
	 * @author Peter Goodman
	 */
	static public function getBase() {
		static $url;

		if(NULL !== $url)
			return $url;

		// remove any query parameters and anchors from the end of the url
		$url = preg_replace('~(\?|#).*$~', '', self::get());

		// remove the route from the end of the URI
		$url = preg_replace(
			'~/?'. preg_quote(trim(Uri::getRoute(), '/')) .'/?$~', 
			'', 
			$url
		);

		return $url;
	}
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
	
	return trim(Url::getBase(), '/') .'/'. trim(preg_replace(
		'~/+~', 
		'/', 
		implode('/', $route_parts)
	), '/');
}
