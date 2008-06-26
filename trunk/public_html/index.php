<?php

/* $Id$ */

list($sm, $ss) = explode(' ', microtime());

require_once dirname(__FILE__) .'/../system/__init__.php';

// pass in where the applications directory is relative to this file
pinq(__FILE__, '../application/');

list($em, $es) = explode(' ', microtime());
echo '<pre>everything time: '. (($em + $es) - ($sm + $ss)) .'</pre>';
