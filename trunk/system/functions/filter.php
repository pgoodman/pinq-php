<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * filter_email(string) -> {FALSE, string}
 *
 * Check if an email is valid. If so, return the email, otherwise return FALSE.
 */
function filter_email($str) {
	if(!preg_match('~^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+.[a-zA-Z]{2,4}$~', $str))
		return FALSE;
	
	$parts = explode('@', $str);
	
	if(!checkdnsrr($parts[1], 'MX'))
		return FALSE;
	
	return $str;
}

function __url_utf8_decode(array $matches) {
	return utf8_decode($matches[2]);
}

/**
 * filter_url(string) -> {FALSE, string}
 *
 * Check if a URL seems valid, if it does return it, otherwise return FALSE.
 * 
 * @note it's possible that a different version of the URL will be returned,
 *       specifically, one that's been filtered, hence the name of the function.
 */
function filter_url($url) {
	
	// get rid of any unwanted characters in the url
	$url = preg_replace('~[^a-z0-9\!#\$%&\'*+-/=?\^_`{|}~@.\[\] ]~', '', $url);
	
	// split up the URL into its parts
	$pattern = (
		"~^(?:(?P<scheme>\w+)://)?". // the scheme
		"(?:(?P<user>[^:]+):(?P<pass>[^@]+)@)?". // username / password
		"(?P<host>(?:(?P<subdomain>[-\w\.]+)\.)?". // subdomain(s)
			"(?P<domain>[-\w]+\.(?P<extension>\w+)))".  // domain + extension
		"(?::(?P<port>\d+))?". // port
		"(?P<path>[\w/]*/(?P<file>\w+(?:\.\w+)?)?)?". // uri
		"(?:\?(?P<query>[^#]+))?". // query params
		"(?:#(?P<fragment>\w+))?~u" // anchor
	);
	
	$matches = array();
	if(!preg_match($pattern, trim($url), $matches))
		return FALSE;
	
	// simple check
	if(!isset($maches['host']) || empty($maches['host']))
		return FALSE;
	
	/*
	// decode utf-8
	$url = preg_replace_callback(
		'~(\&\#|\%[uU])([a-fA-F0-9]{,7});?~', 
		'__url_utf8_decode', 
		$url
	);
	*/
	
	return $url;
}