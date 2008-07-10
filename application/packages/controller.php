<?php

class AppController extends PinqController {
	
	protected $db,
	          $session;
	
	/**
	 * Constructor hook.
	 */
	protected function __init__() {		
		list($this->db, $this->auth) = $this->import('db.blog', 'auth.blog');
		
		$keywords = array('Peter Goodman', 'peter', 'goodman', 'programming');
		$logged_in = FALSE;
		$tags = array();
		
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
		unset($this->session, $this->db);
	}
}