<?php

/**
 * Admin post controller.
 */
class AdminPostController extends PinqController {
	
	public function ANY_index() {
		out('nothing to see here...');
	}
	
	/**
	 * Show the form to create a new blog post.
	 */
	public function GET_create() {
		
	}
	
	/**
	 * Validate the form data and create the blog post.
	 */
	public function POST_create() {
		
		out('you posted to create!');
		
		try {
			$_POST->require('title', 'body', 'tags');
			
		} catch(ValidationException $e) {
			
		}
	}
}
