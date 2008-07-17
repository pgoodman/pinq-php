<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Handle authenticating users against their information stored in a data source
 * and store their logged in information in a session.
 *
 * @author Peter Goodman
 */
class PinqAuth implements ConfigurablePackage {
	
	/**
	 * PinqAuth::configure(PackageLoader, ConfigLoader, array $args) 
	 * -> {Package, void}
	 *
	 * Configure this package for the PackageLoader and return a new instance
	 * of the session class.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {
		
		// load and check the session config
		$config = $config->load('package.auth');
		extract($args);

		// take the first one
		if(0 === $argc)
			$argv[0] = key($config);

		// hrm, no config stuff in the session config files.
		if(!isset($config[$argv[0]])) {
			throw new ConfigurationException(
				"There must be at least one set of configuration settings ".
				"in [package.auth.php]."
			);
		}
		
		// get the config info
		$config = $config[$argv[0]];
		PINQ_DEBUG && expect_array_keys($config, array(
			'data_source', 'model', 'session',
			'field_user_id', 'field_login', 'field_pass', 'field_login_key',
			'hash_function',
		));
		
		// get the gateway and session
		$gateway = $loader->load($config['data_source'])->{$config['model']};
		$session = $loader->load($config['session']);
		
		// an array of where we're storing stuff
		$sess_id = $cookie_id = "auth_{$argv[0]}";
		if(!isset($session[$sess_id]))
			$session[$sess_id] = array();
		
		// return a new authentication class
		return new $class(
			$gateway, 
			$session->offsetGetRef($sess_id), 
			$config,
			$cookie_id
		);
	}
	
	protected $_gateway,
	          $_session,
	          $_config,
	          $_auto_login_cookie;
	
	/**
	 * PinqAuth(Gateway, array $session, array $config, string $cookie_id)
	 */
	public function __construct(Gateway $gateway, 
	                             array &$session, 
	                              array $config,
	                                    $cookie_id) {
		$this->_gateway = $gateway;
		$this->_session = $session;
		$this->_config = $config;
		$this->_auto_login_cookie = $cookie_id;
		
		// auto log in this user?
		if(!$this->isLogged() && isset($_COOKIE[$cookie_id]))
			$this->autoLogin($_COOKIE[$cookie_id]);
	}
	
	/**
	 */
	public function __destruct() {
		unset($this->_gateway);
	}
	
	/**
	 * $a->hash(string) -> string
	 *
	 * Hash the incoming password.
	 */
	protected function hash($password) {
		
		$hash_func = $this->_config['hash_function'];
		if(empty($hash_func))
			$hash_func = 'md5_salted';
		
		// using call_user_func so that $hash_func can be a true PHP callback
		return call_user_func($hash_func, $password);
	}
	
	/**
	 * $a->getUserUpdateQuery(array $config, mixed $user_id, string $login_key)
	 * -> {Query, QueryPredicates}
	 *
	 * Create a query to update the user on login.
	 */
	protected function getUserUpdateQuery(array $config, $user_id, $login_key) {
		return from($config['model'])->set(
			$config['field_login_key'], 
			$login_key
		)->where()->{$config['field_user_id']}->eq($user_id);
	}
	
	/**
	 * $a->setAutoLogCookie(string $value, int $time) -> void
	 */
	protected function setAutoLogCookie($login_key, $time) {
		set_http_cookie(
			$this->_auto_login_cookie, 
			$login_key, 
			time() + $time
		);
	}
	
	/**
	 * $a->updateAndStore(array $config, {Record, array} $user, string 
	 * $login_key[, bool $login]) -> void
	 *
	 * Update the user and store the session.
	 */
	protected function updateAndStore(array $config, 
	                                        $user, 
	                                        $login_key,
	                                        $login = TRUE) {
	
		$user_id = $user[$config['field_login']];
		
		// update the user
		$this->_gateway->update(
			$this->getUserUpdateQuery($config, $user_id, $login_key)
		);
		
		// logging out, clear the session
		if(!$login)
			$this->_session = array();
		
		// logging in, fill up the session
		else {
			$this->_session = array(
				$config['field_login'] => $user_id,
				$config['field_user_id'] => $user[$config['field_user_id']],
			);		
		}
	}
	
	/**
	 * $a->autoLogin(string) -> void
	 *
	 * Attempt to auto login a user with a code stored in the cookie.
	 */
	protected function autoLogin($login_key) {
		
		$config = $this->_config;
		
		if(empty($config['field_login_key']))
			return;
		
		// find the user
		$user = $this->_gateway->select(
			where()->{$config['field_login_key']}->eq($login_key)
		);
		
		// failed to log in the user, get rid of the auto login cookie
		if(NULL === $user) {
			$time = -7200;
			$login_key = '';
		
		// we can auto login the user, get the user and change the login key
		} else {
			$login_key = random_hash();
			$time = (60*60*24*$config['auto_login_days']);
			
			$this->updateAndStore($config, $user, $login_key);
		}
		
		// update or remove the cookie
		$this->setAutoLogCookie($login_key, $time);
	}
	
	/**
	 * $a->login(string $login, string $pass[, bool $auto_login]) -> bool
	 *
	 * Check the incoming values against those in the database. If they are
	 * right then log in the user and return TRUE, otherwise return FALSE.
	 */
	public function login($login, $pass, $auto_login = FALSE) {
		
		// the user is already logged in, log them out
		if($this->isLogged()) {
			throw new InternalErrorException(
				"User already logged in."
			);
		}
		
		$config = $this->_config;
		
		// find the user
		$user = $this->_gateway->select(
			where()->{$config['field_login']}->eq($login)->and->
			{$config['field_pass']}->eq($this->hash($pass))
		);
		
		if($user === NULL)
			return FALSE;
		
		$user_id = $user[$config['field_user_id']];
		
		// should we keep this user auto-logged in?
		if($auto_login && !empty($config['field_login_key'])) {
			$login_key = random_hash();
			$time = (60*60*24*$config['auto_login_days']);
		} else {
			$login_key = '';
			$time = -7200;
		}
		
		// update or remove the auto-login cookie
		$this->setAutoLogCookie($login_key, $time);
		$this->updateAndStore($config, $user, $login_key, TRUE);
		
		return TRUE;
	}
	
	/**
	 * $a->logout(void) -> void
	 *
	 * Log a user out.
	 */
	public function logout() {
		if($this->isLogged())
			$this->updateAndStore($this->_config, $this->_session, '', FALSE);
		
		// clear the cookie
		$this->setAutoLogCookie('', -7200);
	}
	
	/**
	 * $a->isLogged(void) -> bool
	 *
	 * Check if a user is logged in.
	 */
	public function isLogged() {
		return !empty($this->_session);
	}
}