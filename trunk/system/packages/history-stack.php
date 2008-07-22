<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class to handle a stack of URLs in the session.
 *
 * @author Peter Goodman
 * @note This isn't a complete solution to the problem of being able to
 *       redirect to the previous page as it sees history as being strictly a
 *       stack and not a graph.
 */
class PinqHistoryStack extends Stack implements ConfigurablePackage {
	
	/**
	 * PinqHistoryStack::configure(PackageLoader, ConfigLoader, array)
	 * -> PinqHistoryStack
	 *
	 * Configure and return a new instance of this class.
	 */
	static public function configure(Loader $loader, 
	                                 Loader $config, 
	                                  array $args) {
		
		// load and check the session config
		$config = $config->load('package.history-stack');
		extract($args);

		// take the first one
		if(0 === $argc)
			$argv[0] = key($config);
		
		// hrm, no config stuff in the session config files.
		if(!isset($config[$argv[0]]) || !isset($config[$argv[0]]['session'])) {
			throw new ConfigurationException(
				"There must be at least one set of configuration settings ".
				"in [package.history-stack.php]."
			);
		}
		
		// load up the session
		$config = $config[$argv[0]];
		$session = $loader->load($config['session']);
		
		// if there is nothing for this history stack in the session then
		// store the array.
		$sess_id = "history_{$argv[0]}";
		if(!isset($session[$sess_id]))
			$session[$sess_id] = array();
				
		return new $class(
			$session->offsetGetRef($sess_id),
			$config['max_urls']
		);
	}
	
	protected $_max_urls, // max urls allowed in the stack
	          $_exclude; // should the current url be excluded?
	
	/**
	 * PinqHistoryStack(array &$stack, int $max_urls_in_stack)
	 *
	 * A redefination of the Stack constructor as we want to take the array
	 * by reference and require a default array of values.
	 */
	public function __construct(array &$history, $max_urls_in_stack) {
		$this->_stack = &$history;
		$this->_top = count($history)-1;
		$this->_max_urls = (int)$max_urls_in_stack;
		$this->_exclude = FALSE;
		$this->__init__();
	}
	
	/**
	 * @note Stack's destructor is *not* called, this is because the session
	 *       effectively owns this stack.
	 */
	public function __destruct() {
		$this->__del__();
		
		// we need to slice off part of the stack.
		$offset = $this->count() - $this->_max_urls;
		if($offset > 0) {
			$this->_stack = array_slice($this->_stack, $offset);
			$this->_top = $this->_max_urls - 1;
		}
	}
	
	/**
	 * $h->exclude(void) -> void
	 *
	 * Exclude the current page from the history stack.
	 */
	public function exclude() {
		$this->_exclude = TRUE;
	}
	
	/**
	 * $h->pop(void) -> string
	 *
	 * Pop a url off the history stack. If the stack is empty then the base
	 * url is returned.
	 */
	public function pop() {
		if(!$this->isEmpty())
			return parent::pop();
		
		return Url::getBase();
	}
	
	/**
	 * $h->silentPop(void) -> string
	 */
	public function silentPop() {
		return $this->pop();
	}
	
	/**
	 * $h->push(string) -> void
	 *
	 * Push a new url onto the history stack. This will only push a url onto
	 * the stack if the one on top is not the same as the one being pushed
	 * and if the current action/controller is not being excluded.
	 */
	public function push($val) {
		if($this->_exclude || (!$this->isEmpty() && $val == $this->top()))
			return;
		
		parent::push($val);
	}
}