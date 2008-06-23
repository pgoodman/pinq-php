<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

define('DIR_PACKAGE_DATABASE', dirname(__FILE__));

// bring in the needed database files
require_once DIR_PACKAGE_DATABASE .'/exceptions.php';
require_once DIR_PACKAGE_DATABASE .'/data-source.php';
require_once DIR_PACKAGE_DATABASE .'/record-iterator.php';
require_once DIR_PACKAGE_DATABASE .'/query-compiler.php';
require_once DIR_PACKAGE_DATABASE .'/record-gateway.php';
require_once DIR_PACKAGE_DATABASE .'/model-gateway.php';
require_once DIR_PACKAGE_DATABASE .'/model-definition.php';

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
		$layer_dir = DIR_PACKAGE_DATABASE ."/layers/{$driver}";

		// uh oh, the driver file doesn't exist, error
		if(!is_dir($layer_dir)) {
			throw new ConfigurationException(
				"Error in [package.db.php], database driver [{$driver}] ".
				"does not exist or is not supported."
			);
		}

		// bring in the database-specific classes
		require_once "{$layer_dir}/__init__.php";

		// figure out the class name for this database
		$class = class_name($driver) . 'Database';

		// connect to the database
		$database = new $class($host, $user, $pass);
		$database->open($name);
		
		// get the model loader package and have it load any models for this
		// database. note: the argv[0] on here isn't necessary, but it means
		// that each database connection will have its own model loader
		$model_loader = $loader->load("model-loader.db.{$argv[0]}");
		
		// return the database record gateway to the package
		return new DatabaseRecordGateway($database, $model_loader);
	}
}
