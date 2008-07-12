<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Overwrite PHP's super-globals arrays and remove things that we don't want
 * the programmer to have access to. This might be seen as evil. Mainly this
 * is to discourage the use of the $GLOBALS array and to make it safe to use
 * the other various superglobal arrays. Another goal is to harmonize the
 * _SERVER array across different platforms and to deal with XSS stuff.
 * @author Peter Goodman
 */

// fix some things in php's _SERVER array that aren't necessarily consistent
// across platforms or that might be open to manipulation (such as HTTP_HOST)
// by the client
$server = array_merge($_SERVER, array(
	'HTTP_HOST' => get_http_host(),
	'REQUEST_URI' => get_uri(),
	'DOCUMENT_ROOT' => get_document_root(),
	'SCRIPT_FILENAME' => get_script_filename(),
	'HTTPS' => (get_http_scheme() == 'https' ? 'on' : 'off'),
	'REMOTE_ADDR' => get_user_ip(),
	'REQUEST_METHOD' => get_request_method(),
));

// overwrite the $GLOBALS array, then extract the new super globals by
// reference into the current scope, overwriting the shorthand to the
// normal superglobals.
$GLOBALS = NULL;
unset($GLOBALS);

extract(
	array(
		'_SERVER' => $server,
		'_FILES' => new ReadOnlyDictionary($_FILES),
		'_REQUEST' => NULL,
	), 
	EXTR_OVERWRITE | EXTR_REFS
);
