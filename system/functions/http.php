<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * get_http_host(void) -> string
 *
 * Attempt to get the current http host. This function first tries to find the
 * host and then validates it against the (partial) host given in the main
 * PINQ application configuration file. If the host fails to validate then
 * a YieldResourceException is thrown.
 */
function get_http_host() {
	static $host;
	
	// make sure to only check if the host is valid once
	if(NULL !== $host)
		return $host;
		
	// so this is browser set, we need to make sure that it is in fact us.
	$host = get_env('HTTP_HOST');
	$hack = FALSE;
	
	// validate by regular expression
	if(defined('PINQ_HTTP_HOST'))
		$hack = !preg_match('~(\.?[^.]+)*'. PINQ_HTTP_HOST .'$~', $host);
		
	// validate by looking up the ip. this isn't full-proof as it will likely
	// fail miserably if the server is behind a proxy of some sort.
	else
		$hack = $host !== trim(gethostbyaddr(get_env('SERVER_ADDR')));
	
	// is this a possible hacking attempt?
	if($hack) 
		yield(ERROR_403); // forbidden
	
	return $host;
}

/**
 * get_http_scheme(void) -> {http, https}
 *
 * Return the HTTP scheme currently in use. This function returns the scheme
 * in lower case.
 *
 * @author Peter Goodman
 */
function get_http_scheme() {
	static $scheme;
	
	if($scheme !== NULL)
		return $scheme;
		
	if('on' == strtolower(get_env('HTTPS')))
		$scheme = 'https';
	else if(0 === stripos(get_env('SCRIPT_URI'), 'https'))
		$scheme = 'https';
	else
	 	$scheme = 'http';
	
	return $scheme;
}

/**
 * set_http_status(int $code) -> void
 *
 * Set the HTTP header status code.
 */
function set_http_status($code) {
	
	// messages for the various status codes
	static $codes;
	
	// cache the messages, fairly complete list based off of:
	// http://www.askapache.com/htaccess/apache-status-code-headers-errordocu
	// ment.html#apache-response-codes-57
	// thanks CakePHP for doing most of the heavy lifting, only a few were
	// missing!
	if(NULL === $codes) {
		$codes = array(
			100 => "Continue",
			101 => "Switching Protocols",
			102 => "Processing",
			200 => "OK",
			201 => "Created",
			202 => "Accepted",
			203 => "Non-Authoritative Information",
			204 => "No Content",
			205 => "Reset Content",
			206 => "Partial Content",
			207 => "Multi-Status",
			300 => "Multiple Choices",
			301 => "Moved Permanently",
			302 => "Found",
			303 => "See Other",
			304 => "Not Modified",
			305 => "Use Proxy",
			307 => "Temporary Redirect",
			400 => "Bad Request",
			401 => "Unauthorized",
			402 => "Payment Required",
			403 => "Forbidden",
			404 => "Not Found",
			405 => "Method Not Allowed",
			406 => "Not Acceptable",
			407 => "Proxy Authentication Required",
			408 => "Request Time-out",
			409 => "Conflict",
			410 => "Gone",
			411 => "Length Required",
			412 => "Precondition Failed",
			413 => "Request Entity Too Large",
			414 => "Request-URI Too Large",
			415 => "Unsupported Media Type",
			416 => "Requested range not satisfiable",
			417 => "Expectation Failed",
			422 => "Unprocessable Entity",
			423 => "Locked",
			424 => "Failed Dependency",
			425 => "No Code",
			426 => "Upgrade Required",
			500 => "Internal Server Error",
			501 => "Not Implemented",
			502 => "Bad Gateway",
			503 => "Service Unavailable",
			504 => "Gateway Time-out",
			505 => "HTTP Version Not Supported",
			506 => "Variant Also Negotiates",
			507 => "Insufficient Storage",
			510 => "Not Extended",
		);
	}
	
	if(headers_sent()) {
		throw new InternalErrorException(
			"Can't set HTTP status after headers have been sent."
		);
	}
	
	$code = (int)$code;
	$message = $codes[$code];
	
	// invalid http status code, do nothing
	if(!isset($codes[$code]))
		return;
	
	// set the header
	header("HTTP/1.1 {$code} {$message}", TRUE);
	header("Status: {$code} {$message}", TRUE);
}

/**
 * redirect(string $location[, bool $as_url]) ! HttpRedirectException
 *
 * Do a HTTP redirect. If $as_url is TRUE then it means we're redirecting to
 * a url and not a route.
 *
 * @author Peter Goodman
 */
function redirect($location, $as_url = FALSE) {
	
	// we throw an exception instead of redirecting so that when the exception
	// is caught we can tear down any existing resources properly
	throw new HttpRedirectException($location, $as_url);
}
