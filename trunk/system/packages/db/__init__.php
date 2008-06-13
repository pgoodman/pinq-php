<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// if we're dealing with multiple databases, we will forgo including the files
// again and defining the constant
if(!defined('DIR_PACKAGE_DATABASE')) {

	define('DIR_PACKAGE_DATABASE', dirname(__FILE__));

	// import what's needed
	require_once DIR_PACKAGE_DATABASE .'/exceptions.php';
	require_once DIR_PACKAGE_DATABASE .'/database.php';
	require_once DIR_PACKAGE_DATABASE .'/database-record-iterator.php';
	require_once DIR_PACKAGE_DATABASE .'/database-record.php';
	
	// ORM-ish things
	require_once DIR_PACKAGE_DATABASE .'/database-query.php';
	require_once DIR_PACKAGE_DATABASE .'/database-finder.php';
}

// load the config stuff
$info = $config->load('package.db');

// oh noes, no configuration settings for this database alias exists
if($argc === 0) {
	throw new UnexpectedValueException(
		"No database name passed to import()."
	);
}

// we've been given a database alias but it hasn't been configured
if(!isset($info[$argv[0]])) {
	throw new UnexpectedValueException(
		"No configuration information exists for the [{$argv[0]}] ".
		"database. Please check the [package.db.ini.php] file."
	);
}

// bring the database config info into the current scope
extract($info[$argv[0]]);

// figure out the driver name and the file its located in
$driver = strtolower($driver);
$layer_dir = DIR_PACKAGE_DATABASE ."/layers/{$driver}";

// uh oh, the driver file doesn't exist, error
if(!is_dir($layer_dir)) {
	throw new ConfigurationException(
		"Error in [package.db.ini.php], database driver [{$driver}] does ".
		"not exist or is not supported."
	);
}

// bring in the database-specific classes
require_once "{$layer_dir}/database.php";
require_once "{$layer_dir}/database-record-iterator.php";
require_once "{$layer_dir}/database-record.php";

// figure out the class name for this database
$class = class_name($driver) . 'Database';

// connect to the database
$database = new $class($host, $user, $pass);
$database->open($name);

// return a database finder
$package = new DatabaseFinder($database);
