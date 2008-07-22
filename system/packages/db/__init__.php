<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

define('DIR_PACKAGE_DATABASE', dirname(__FILE__));

// bring in the needed database files
require_once DIR_PACKAGE_DATABASE .'/exceptions.php';

/**
 * Class for the database package to configure itself.
 * @author Peter Goodman
 */
class PinqDb implements ConfigurablePackage {
	
	/**
	 * Configure this package.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {
		
		// make sure the arguments passed into this package from the loader
		// has the information that we expect
		PINQ_DEBUG && expect_array_keys($args, array(
			'argv', 'argc', 'class',
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
			throw new ConfigurationException(
				"No configuration information exists for the [{$argv[0]}] ".
				"database. Please check the [package.db.php] file."
			);
		}
		
		// make sure that the config array has the information that we expect 
		// to extract from it
		PINQ_DEBUG && expect_array_keys($info[$argv[0]], array(
			'driver', 'host', 'user', 'pass', 'name', 'port',
		));
		
		// bring the database config info into the current scope
		extract($info[$argv[0]]);

		// figure out the driver name and the file its located in
		$driver = strtolower($driver);
		
		// create a new package loader for this database driver
		$packages = self::getPackageLoader($config, $driver);
		$packages->load('model.relational.definition');

		// connect to the database
		$database = $packages->loadNew('resource', array($packages));
		$database->connect($host, $user, $pass, $name);
		
		// get a relations object, this stores up relations
		$relations = $loader->loadNew('model.relational.manager');
		
		// set up the gateway
		$gateway = $packages->loadNew('model.relational.gateway', array(
			$database,
			self::getTypeHandler($loader),
		));
		$gateway->setRelations($relations);
		$gateway->setModelDictionary(
			$loader->loadNew('model.relational.dictionary', array(
				DIR_APPLICATION ."/models/db/{$argv[0]}", 
				$relations,
			))
		);
		
		return $gateway;
	}
	
	/**
	 * PinqDb::getTypeHandler(PackageLoader) -> PinqTypeHandler
	 *
	 * Create a type handler for input to gateways and add various types
	 * to it.
	 */
	static protected function getTypeHandler(Loader $loader) {
		$handler = $loader->loadNew('type-handler');
		
		$handler->handleObject(
			'Query', $loader->load('model.relational.handler.query')
		);
		
		$handler->handleObject(
			'QueryPredicates', $loader->load(
				'model.relational.handler.query-predicates'
			)
		);
		
		$handler->handleObject(
			'Record', $loader->load('model.relational.handler.record')
		);
		
		$handler->handleScalar(
			'string', $loader->load('model.handler.string')
		);
		
		return $handler;
	}
	
	/**
	 * PinqDb::getPackageLoader(ConfigLoader, string $driver) -> PackageLoader
	 *
	 * Return a new package loader that will query the sub-package structure
	 * of the db package and also get top-level packages when possible.
	 */
	static protected function getPackageLoader(Loader $config, $driver) {
		
		$driver_class = class_name($driver);
		
		return new PackageLoader(
			$config,
			// where to look for the files
			array(
				DIR_APPLICATION ."/packages/db/{$driver}",
				DIR_SYSTEM ."/packages/db/{$driver}",
		
				DIR_APPLICATION ."/packages/db/packages",
				DIR_SYSTEM ."/packages/db/packages",
		
				DIR_APPLICATION .'/packages/',
				DIR_SYSTEM .'/packages/',
			),
			
			// how to prefix any classes found
			array(
				"App{$driver_class}Db",
				"Pinq{$driver_class}Db",
		
				'AppDb',
				'PinqDb',
		
				'App',
				'Pinq',
			)
		);
	}
}
