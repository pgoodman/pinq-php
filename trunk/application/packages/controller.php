<?php

class AppController extends PinqController {
	
	protected $db;
	
	/**
	 * Constructor hook.
	 */
	protected function __init__() {
		$this->db = $this->import('db.blog');
		
		$tags = $this->db->tags->getPopular();
		
		$keywords = array('Peter Goodman', 'peter', 'goodman', 'programming');
		foreach($tags as $tag)
			$keywords[] = $tag['name'];
		
		$this->layout[] = array(
			'blog_name' => 'I/O Reader',
			'blog_description' => '',
			'blog_keywords' => $keywords,
			'blog_author' => 'Peter Goodman',
			'tags' => $tags,
		);
	}
	
	/**
	 * Destructor hook.
	 */
	protected function __del__() {
		unset($this->db);
	}
}