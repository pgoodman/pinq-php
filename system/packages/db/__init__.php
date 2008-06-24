<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

define('DIR_PACKAGE_DATABASE', dirname(__FILE__));

// bring in the needed database files
require_once DIR_PACKAGE_DATABASE .'/exceptions.php';
require_once DIR_PACKAGE_DATABASE .'/data-source/data-source.php';
require_once DIR_PACKAGE_DATABASE .'/data-source/record-iterator.php';
require_once DIR_PACKAGE_DATABASE .'/data-source/record.php';
require_once DIR_PACKAGE_DATABASE .'/model/model-gateway.php';
require_once DIR_PACKAGE_DATABASE .'/model/model-definition.php';
require_once DIR_PACKAGE_DATABASE .'/pql/query-compiler.php';

/**
 * Class for the database package to configure itself.
 * @author Peter Goodman
 */
class PinqDb implements ConfigurablePackage {
	
	/**
	 * Configure this package.
	 */
	static public function configure(Loader $loader, Loader $config, array $args) {
		
		// make sure the arguments passed into this package from the loader
		// has the information that we expect
		DEBUG_MODE && expect_array_keys($args, array(
			'argv',
			'argc',
			'class',
			'key',
		));
		
		// bring out the above variables for convenience
		extract($args);
		
		// load the config stuff
		$info = $config->load('package.db');
		
		// take the first db config by default
		if(!empty($info) && $argc === 0)
			$argv[0] = key($info);
		
		// we've been given a database alias but it hasn't been configured
		// if $argv[0] also doesn't exist then the isset will fail, which is
		// nice
		if(!isset($info[$argv[0]])) {
			throw new UnexpectedValueException(
				"No configuration information exists for the [{$argv[0]}] ".
				"database. Please check the [package.db.php] file."
			);
		}
		
		// make sure that the config array has the information that we expect 
		// to extract from it
		DEBUG_MODE && expect_array_keys($info[$argv[0]], array(
			'driver',
			'host',
			'user',
			'pass',
			'name',
			'port',
		));
		
		// bring the database config info into the current scope
		extract($info[$argv[0]]);

		// figure out the driver name and the file its located in
		$driver = strtolower($driver);
		$file = DIR_PACKAGE_DATABASE ."/data-source/{$driver}/__init__.php";

		// uh oh, the driver file doesn't exist, error
		if(!file_exists($file)) {
			throw new ConfigurationException(
				"Error in [package.db.php], database driver [{$driver}] ".
				"does not exist or is not supported."
			);
		}

		// bring in the database-specific classes
		require_once $file;

		// figure out the class name for this database
		$class = class_name($driver) . 'Database';

		// connect to the database
		$database = new $class($host, $user, $pass);
		$database->open($name);
		
		// get a new relations dictionary and figure out which directory that
		// models will be in
		$relations = new ModelRelations;
		$models_dir = DIR_APPLICATION ."/models/db/{$argv[0]}";
		
		// return the database model gateway to the package
		return new DatabaseModelGateway(
			new ModelDictionary($relations, $models_dir),
			$relations,
			$database,
			NULL
		);
	}
}
