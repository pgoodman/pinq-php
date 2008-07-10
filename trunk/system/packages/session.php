<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

// session settings
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

/**
 * destroy_php_session(void) -> void
 *
 * Destroy session data and session cookie data.
 */
function destroy_php_session() {
	@setcookie(session_name(), '', time() - t2000, '/');
	@session_destroy();
}

/**
 * Represents a PINQ session. This is just a basic dictionary wrapper around
 * the session super global. If a data source is used then more than one
 * sessions can be managed simultaneously. When datasources are used, PHP's
 * session_set_save_handler() is essentially re-invented to make sure desired
 * behavior occurs. Also, when a data-source is used, the session ids are
 * rotated per request.
 *
 * @author Peter Goodman
 */
class PinqSession extends OuterRecord implements ConfigurablePackage {
	
	/**
	 * PinqSession::configure(PackageLoader, ConfigLoader, array $args) 
	 * -> {Package, void}
	 *
	 * Configure this package for the PackageLoader and return a new instance
	 * of the session class.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {
		extract($args);

		// load and check the session config
		$config = $config->load('package.session');
		extract($args);
		
		// take the first one
		if(0 === $argc)
			$argv[0] = key($config);
		
		// hrm, no config stuff in the session config files.
		if(!isset($config[$argv[0]])) {
			throw new ConfigurationException(
				"There must be at least one set of configuration settings ".
				"in [package.session.php]."
			);
		}
		
		// debug the config
		$config = $config[$argv[0]];
		PINQ_DEBUG && expect_array_keys($config, array(
			'data_source', 'model', 'field_id', 'field_data', 'field_time'
		));
		
		$gateway = $query = $record = NULL;
		$gc_time = ini_get('session.gc_maxlifetime');
		$use_php_session = empty($config['data_source']);
		$cookie_name = $use_php_session ? session_name() : $argv[0];
		$create = !isset($_COOKIE[$cookie_name]);
		
		// we're just using existing session as a data storage
		if($use_php_session) {
			
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
			
			// load up a gateway
			$gateway = $loader->load($config['data_source']);
			
			// get the garbage collection probability
			$p = ini_get('session.gc_probability') / ini_get('session.gc_divisor');
			$p = 100 * $p;
			
			// garbage collect
			if($p >= mt_rand(1, 100)) {
				$gateway->delete(
					from($config['model'])->where()->
					{$config['field_time']}->lt(time() - $gc_time)
				);
			}
			
			// create a query that is acceptable for all session-related
			// operations
			$query = from($config['model'])->select(ALL)->set(array(
				$config['field_id'] => _,
				$config['field_data'] => _,
				$config['field_time'] => time(),
			));
			$query->where()->{$config['field_time']}->geq(time() - $gc_time)
			               ->and->{$config['field_id']}->eq(_);
						
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
						$current_id = $_COOKIE[$cookie_name];
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
					$record['cookie_name'] = $cookie_name;
					
					// set the cookie to the next session id, the db values
					// will be updated on __destruct
					set_http_cookie(
						$cookie_name, 
						$next_id, 
						time() + $gc_time
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
				
				unset_http_cookie($cookie_name);
				throw $e;
			}
			
			// reference the field that stores the data in the session record
			$data = &$record->offsetGetRef($config['field_data']);
		}
		
		return new $class($record, $data, $gateway, $query);
	}
	
	protected $_gateway,
	          $_data,
	          $_query;
	
	/**
	 * PinqSession(Record, array &$data[, Gateway[, Query]])
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
		
		else {
			
			// update the session and change the session id to match the id
			// that's in the cookie
			$this->_gateway->update(
				$this->_query,
				array(
					parent::offsetGet('next_session_id'),
					$this->_data, 
					parent::offsetGet('curr_session_id')
				)
			);			
			
			// clean up
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
			destroy_php_session();
		else {
			$this->_gateway->delete($this->_query);
			unset_http_cookie(parent::offsetGet('cookie_name'));
		}
	}
}
