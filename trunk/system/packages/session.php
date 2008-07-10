<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// session settings
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

/**
 * empty_string(void) -> string
 */
function empty_string() {
	return '';
}

/**
 * destroy_session(void) -> void
 *
 * Destroy session data and session cookie data.
 */
function destroy_session() {
	@setcookie(session_name(), '', time() - 42000, '/');
	@session_destroy();
}

/**
 * Represents a PINQ session. This is just a basic dictionary wrapper around
 * the session super global.
 *
 * @author Peter Goodman
 */
class PinqSession extends OuterRecord implements ConfigurablePackage {
	
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
		
		PINQ_DEBUG && expect_array_keys($config, array(
			'data_source', 'model', 'field_id', 'field_data',
		));
		
		$gateway = $query = $record = NULL;
		$name = session_name();
		$time = time() + ini_get('session.gc_maxlifetime');
				
		$create = !isset($_COOKIE[$name]);
		$recreated = FALSE;
		
		// we're just using existing session as a data storage
		if(empty($config['data_source'])) {
			
			// start the session if it hasn't been started yet
			if(!isset($_SESSION) || '' === session_id())
				session_start();
			
			// the session is not a PinqSession object
			if($create || get_class($_SESSION) !== $class) {
				session_id(random_hash());
				$data = array();
				$record = new InnerRecord($data);
			
			// we've already created the session record object, return it
			} else
				return $_SESSION;
			
		// we're using some data source. We won't even use any of the session
		// functions to deal with this and instead just manually manage the
		// cookie
		} else {
			
			// little hack :P
			session_write_close();
			
			// load up a gateway
			$gateway = $loader->load($config['data_source']);
			
			// create a query that is acceptable for all session-related
			// operations
			$query = from($config['model'])->select(ALL)->set(array(
				$config['field_id'] => _,
				$config['field_data'] => _,
				$config['field_time'] => time(),
			));
			$query->where()->{$config['field_id']}->eq(_);
						
			try {
				do {
					// create a session record
					if($create) {
						$current_id = random_hash();
						$gateway->insert($query, array(
							$current_id,
							array(),
						));
					
					// a session record probably exists
					} else {
						$current_id = $_COOKIE[$name];
					}
					
					// get an existing session record from before or the one
					// we just created
					$record = $gateway->get($query, array($current_id));
					
					// bad record, will only happen after not creating a record
					if($record === NULL) {
						$create = TRUE;
						continue;
					}
					
					// figure out the next session id
					$next_id = random_hash();
					
					// store the current and next session ids
					$record['curr_session_id'] = $current_id;
					$record['next_session_id'] = $next_id;
					
					// set the cookie to the next session id
					set_http_cookie(
						$name, 
						$next_id, 
						$time
					);
					
					break;
					
				} while(TRUE);
				
			// there was a problem, try to destroy any unwanted database
			// records. This problem could come from anywhere: the query, the
			// cookie setting, anything.
			} catch(Exception $e) {
				
				$gateway->delete(
					$query->where()->or->{$config['field_id']}->eq(_), 
					array($next_id, $current_id)
				);
				
				unset_http_cookie($name);
				throw $e;
			}
			
			$data = &$record->offsetGetRef($config['field_data']);
		}
		
		return new $class($record, $data, $gateway, $query);
	}
	
	protected $_gateway,
	          $_data,
	          $_query;
	
	/**
	 * PinqSession([Gateway], Record, array &$data[, Query])
	 */
	public function __construct(Record $record,
	                            array &$data,
	                           Gateway $gateway = NULL, 
	                             Query $query = NULL) {
		
		parent::__construct($record);
		$this->_gateway = $gateway;
		$this->_data = &$data;
		$this->_query = $query;
	}
	
	/**
	 */
	public function __destruct() {
		
		if(NULL === $this->_gateway)
			session_write_close();
		
		// update the datasource. note: when a data-source is used as the
		// storage means 
		else {
			$this->_gateway->update(
				$this->_query,
				array(
					parent::offsetGet('next_session_id'),
					$this->_data, 
					parent::offsetGet('curr_session_id')
				)
			);
			unset($this->_gateway, $this->_query);
		}
				
		parent::__destruct();
	}
	
	/**
	 * $s->offsetGet(string $key) <==> $s[$key] -> mixed
	 */
	public function offsetGet($key) {
		if(isset($this->_data[$key]))
			return $this->_data[$key];
		
		return NULL;
	}
	
	/**
	 * $s->offsetSet(string $key, mixed $val) <==> $s[$key] = $val -> void
	 */
	public function offsetSet($key, $val) {
		if(NULL === $key && is_array($val)) {
			$this->_data = array_merge($this->_data, $val);
		
		} else		
			$this->_data[$key] = $val;
	}
	
	/**
	 * $s->offsetExists(string $key) <==> isset($s[$key]) -> bool
	 */
	public function offsetExists($key) {
		return isset($this->_data[$key]);
	}
	
	/**
	 * $s->offsetUnset(string $key) <==> unset($s[$key]) -> void
	 */
	public function offsetUnset($key) {
		unset($this->_data[$key]);
	}
	
	/**
	 * $s->regenerate(void) -> void
	 *
	 * Regenerate the session id. Use this instead of session_regenerate_id().
	 */
	public function regenerate() {
		if(NULL === $this->_gateway)
			session_regenerate_id(TRUE);		
	}
	
	/**
	 * $s->destroy(void) -> void
	 *
	 * Destroy the session record data.
	 */
	public function destroy() {
		if(NULL === $this->_gateway)
			destroy_session();
		else {
			$this->_gateway->delete($this->_query);
			unset_http_cookie(session_name());
		}
	}
}
