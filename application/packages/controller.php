<?php

class AppController extends PinqController {
	
	protected $db;
	
	/**
	 * Constructor hook.
	 */
	protected function __init__() {		
		$this->db = $this->import('db.blog');
		
		
		$keywords = array('Peter Goodman', 'peter', 'goodman', 'programming');
		$logged_in = FALSE;
		
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
		unset($this->db);
	}
}