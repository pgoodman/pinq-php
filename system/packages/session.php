<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

function empty_string() {
	return '';
}

/**
 * Represents a PINQ session. This is just a basic dictionary wrapper around
 * the session super global.
 *
 * @author Peter Goodman
 */
class PinqSession extends OuterRecord implements ConfigurablePackage {
	
	protected $_history,
	          $_gateway,
	          $_id,
	          $_regenerated = FALSE;
	
	/**
	 * PinqRouteParser::configure(PackageLoader, ConfigLoader, array $args) 
	 * -> {Package, void}
	 *
	 * Configure this package for the PackageLoader and return a new instance
	 * of the route parser.
	 *
	 * @note When extending this class, there is no need to change this method
	 *       as the class bane to be instantiated is passed in.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {

		extract($args);

		// load and check the session config
		$config = $config->load('package.session');
		
		if(!isset($config['session']['data_source'])) {
			throw new ConfigurationException(
				"Session configuration file must contain array with ".
				"[session][data_source]."
			);
		}
		
		// session settings
		ini_set('session.use_only_cookies', 1);
		ini_set('session.use_trans_sid', 0);
		
		$gateway = $record = NULL;
		$create = !isset($_COOKIE[session_name()]);
		
		// we're just using existing session as a data storage
		if(NULL === $config['data_source']) {
			
			// start the session
			if(!isset($_SESSION) || '' === session_id())
				session_start();
			
			if($create) {
				$_SESSION = new InnerRecord($_SESSION);
			
		// we're using some data source
		} else {
			
			PINQ_DEBUG && expect_array_keys($config, array(
				'data_source', 'model', 'field_id', 'field_data',
			));
			
			// this is a hack of sorts because php's session handling stuff is
			// annoying at times.
			$fn = 'empty_string';
			session_set_save_handler($fn, $fn, $fn, $fn, $fn, $fn);
			session_start();
			$id = session_id();
			session_write_close();
			
			// load up the gateway
			$ds = $loader->load($config['data_source']);
			$gateway = $ds->__get($config['model']);
			
			// create a new entry for this session
			if($create) {
				
				$record = $gateway->createRecord(array(
					$config['field_id'] => $id,
					$config['field_data'] => array(),
				));
				
				$gateway->insert($record);
				
			// get an existing record from the gateway	
			} else 
				$record = $gateway->getBy($config['field_id'], session_id());
		}

		return new $class($gateway, $record);
	}
	
	/**
	 * PinqSession([Gateway], Record)
	 */
	public function __construct(Gateway $gateway = NULL, Record $record) {
		
		parent::__construct($record);
		
		$this->_id = session_id();
		$this->_gateway = $gateway;
		
		$_SESSION = $this;
	}
	
	/**
	 */
	public function __destruct() {
		
		// the session id was regenerated
		if($this->_regenerated) {
			
		}
		
		if(!$this->_gateway)
			session_write_close();
		
		// we *might* need to update the data source
		else {
			if(!$this->isSaved())
				$this->_gateway->update($this->getRecord());
			
			unset($this->_gateway);
		}
		
		parent::__destruct();
	}
	
	/**
	 * $s->regenerate(void) -> void
	 *
	 * Regenerate the session id.
	 */
	public function regenerate() {
		session_regenerate_id(TRUE);
		$this->_regenerated = TRUE;
	}
}
