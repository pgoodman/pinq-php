<?php

/**
 * Admin post controller.
 */
class AdminPostResourceText extends AppResourceText {
	
	/**
	 * Show the form to create a new blog post.
	 */
	public function GET_create() {
		
	}
	
	/**
	 * Validate the form data and create the blog post.
	 */
	public function POST_create() {
				
		try {
			$_POST->require('title', 'body', 'tags');
			
		} catch(ValidationException $e) {
			
		}
	}
}
