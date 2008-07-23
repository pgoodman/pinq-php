<?php

/* $Id$ */

list($sm, $ss) = explode(' ', microtime());

try {
	require_once dirname(__FILE__) .'/../system/__init__.php';

	// pass in where the applications directory is relative to this file
	pinq(__FILE__, '../application/');

// catch ALL exceptions that have bubbled up this far. We hope there are 
// none but there's no guarantee.
} catch(Exception $e) {
	echo $e->getMessage();
	echo '<pre>';
	print_r(array_slice($e->getTrace(), 0, 3));
	echo '</pre>';
}

list($em, $es) = explode(' ', microtime());
echo '<pre>everything time: '. (($em + $es) - ($sm + $ss)) .'</pre>';
