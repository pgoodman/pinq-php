<?php

require_once DIR_SYSTEM .'/dictionary.php';

// make sure we have a session running
if('' == session_id())
	session_start();

// this is a POST request, put the appropriate things into the stack
// and redirect back here. Assuming this file is included first somewhere,
// then this 
if(!empty($_POST)) {
	$_SESSION[Http::TEMPORARY_POST] = array($_POST, $_SERVER);
	header("Location: ". HttpHistory::currentUrl());
}

// register the first shutdown function. we do this so that if any other
// scripts register their own shutdown functions they will not be executed.
// yes, I'm that evil.
register_shutdown_function("exit");

/**
 * Handle HTTP Requests.
 */
class Http {
	
	// the important places that we store things in the session
	const CONTINUATIONS = '__continuations__', // serialized continuations
		  TEMPORARY_POST = '__post__'; // temporary post variables
	
	// inputs
	protected $session,
			  $post,
			  $get,
			  $files,
			  $cookie;
	
	protected $ids, // all of the previous continuation ids
			  $continuations = array(), // all of the continuations
			  $cc; // the current continuation
	
	/**
	 * Initialize all the HTTP information we will need to handle
	 * continuations.
	 */
	public function __construct() {
		
		// get all the information that's relevant to this state. we don't
		// care about _SERVER or _ENV as it would be redundant to save those
		// and because they are useful to the programmer
		$this->session = &$_SESSION;
		$this->post = &$_POST;
		$this->get = &$_GET;
		$this->cookie = &$_COOKIE;
		$this->files = &$_FILES;
		
		// there are no continuations in existance yet, create an array for
		// them
		if(!isset($this->session[self::CONTINUATIONS])) {
			$this->session[self::CONTINUATIONS] = array();
			$this->session[self::IDS] = array();
		}
		
		// bring in all the continuations
		$this->continuations = &$this->session[self::CONTINUATIONS];
		$this->ids = &$this->session[self::IDS];
		
		// defaulting the last_id to something that will improbably match
		// any existing continuations is useful for when there aren't any
		// continuations or when the http referer doesn't exist
		$last_id = md5(uniqid(rand(), true));
		
		// the http referer exists, hash it into a usable id
		if(isset($_SERVER['HTTP_REFERER']))
			$last_id = $this->getId($_SERVER['HTTP_REFERER']);
		
		// figure out which continuation to work with. if one isn't matched
		// then create one and use it
		if(!isset($this->continuations[$last_id]))
			$this->continuations[$last_id] = $this->createContinuation();
				
		// okay, now set the current continuation, and change the continuation
		// id to be the id for this page.
		$this->cc = &$this->continuations[$last_id];
		unset($this->continuations[$last_id]);
		$this->continuations[$this->getId($last_id)] = &$this->cc;
	}
	
	/**
	 * Destructor, store the continuations back to the session.
	 */
	public function __destruct() {
		
		// go through the existing continuations and drop any invalid ones
		foreach($this->continuations as $id => $continuation) {
			if(!$continuation->valid())
				unset($this->continuations[$id]);
		}
				
		// store the continuations in the session and close the session
		$this->session[self::CONTINUATIONS] = $this->continuations;
		session_write_close();
	}
	
	/**
	 * Create a continuation.
	 */
	protected function &createContinuation() {
		$session = array();
		$continuation = new HttpContinuation($session);
		return $continuation;
	}
	
	/**
	 * Get the identifier for the current page.
	 */
	protected function getId($url = NULL) {
		return md5($url ? $url : HttpHistory::currentUrl());
	}
}

/**
 * Class for maintaining continuation-like things accross distinct HTTP
 * requests. The idea is to make the requests stateful as opposed to stateless.
 * @author Peter Goodman
 */
class HttpContinuation implements Continuation, Stateful {
	
	// constants for accessing information from the session
	const POSITION = '__position__',
		  STATES = '__states__';
	
	// all the information we need :)
	protected $states = array(),
		      $position, // this is a moving target
		      $session; // this isn't _SESSION, but something in it
	
	/**
	 * Constructor, initialize state information from the session and create
	 * an entry for this state.
	 */
	public function __construct(array &$session) {
		
		$this->session = &$session;
		
		// nothing is stored in the "session" so set up our state
		if(!isset($session[self::STATES])) {
			$session[self::STATES] = array();
			$session[self::POSITION] = -1;
		}
		
		// populate this class with the necessary info it needs to maintain
		// states
		$this->states = &$this->session[self::STATES];
		$this->position = $this->session[self::POSITION];
		
		// clean up the states and create the current state
		$this->cleanUp();
		$this->createState();
	}
	
	/**
	 * Store this states back in the session.
	 */
	public function __destruct() {
		$this->session[self::STATES] = $this->states;
		$this->session[self::POSITION] = $this->position;
	}
	
	/**
	 * Trim down the number of existing states.
	 */
	protected function cleanUp() {
		
	}
	
	/**
	 * Store the current state.
	 */
	protected function createState() {
		++$this->position;
	}
	
	/**
	 * Load a different HTTP state. This can be tricky because if we go to
	 * a state that is further than one away, we potentially need to load up
	 * the intermediate states in the backround and account for their side-
	 * effects.
	 */
	public function goto(HttpState $state) {
		
	}
	
	/**
	 * Get the previous state.
	 */
	public function prev() {
		return $this->getState(-1);
	}
	
	/**
	 * Return information about the current state.
	 */
	public function current() {
		return $this->getState();
	}
	
	/**
	 * Get information about the next state.
	 */
	public function next() {
		return $this->getState(1);
	}
	
	/**
	 * Is this continuation still valid? If not we will discard it.
	 */
	public function valid() {
		
		return TRUE;
	}
	
	/**
	 * Get a state.
	 */
	public function getState($offset = 0) {
		$position = $this->position + $offset;
		if(isset($this->states[$position]))
			return $this->states[$position];
		return NULL;
	}
}

/**
 * Class representing a given state.
 */
class HttpState extends Dictionary {
	
	// http request types
	const REQUEST_POST = 1,
	      REQUEST_GET = 2,
	      REQUEST_PUT = 4;
		  
	public $request_type, // what type of request was this?
		   $created; // information that was sent during the request
}

/**
 * Manage a stack of the HTTP history.
 */
class HttpHistory extends Stack {
	
	/**
	 * Return the current URL.
	 */
	static public function currentUrl() {
		
	}
}
