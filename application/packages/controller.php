<?php

class AppController extends PinqController {
	
	protected $db,
	          $session,
	          $exclude_from_history = FALSE;
	
	/**
	 * Constructor hook.
	 */
	protected function __init__() {
		
		// set up the basic template stuff
		$keywords = array('Peter Goodman', 'peter', 'goodman', 'programming');
		$logged_in = FALSE;
		$tags = array();
		
		// bring in the data source and authentication class
		list($this->db, $this->auth, $this->history) = $this->import(
			'db.blog', 'auth.blog', 'history-stack.blog'
		);
		
		try {
			
			// get the popular tags and add them as keywords for the HTML meta
			// tag
			$tags = $this->db->tags->getPopular();
			foreach($tags as $tag)
				$keywords[] = $tag['name'];
			
			// figure out if this user is logged in / auto log them in
		
		} catch(Exception $e) { }
		
		$this->layout[] = array(
			'blog_name' => 'I/O Reader',
			'blog_description' => '',
			'blog_keywords' => $keywords,
			'blog_author' => 'Peter Goodman',
			'tags' => $tags,
			'logged_in' => $logged_in,
		);
	}
	
	/**
	 * Destructor hook.
	 */
	protected function __del__() {
		$this->history->push(get_url());
		unset($this->auth, $this->history, $this->db);
	}
}