<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

$dir = dirname(__FILE__);

// bring in the Pinq Query Language, a.k.a. PINQ: PHP Integrated Query. Yes, I
// know that it is also the name of the framework, I just really liked it!
require_once $dir .'/abstract-relation.php';
require_once $dir .'/abstract-model.php';
require_once $dir .'/abstract-query.php';
require_once $dir .'/abstract-predicates.php';
require_once $dir .'/concrete-query.php';
require_once $dir .'/model-dictionary.php';
