<?php

/**
 * Index controller.
 */
class IndexController extends PinqController {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY_index() {
		
		$db = $this->import('db.blog');
		$post = $db->posts->getNewest();
		
		// set stuff to the view
		$this->view[] = array(
			
			// get the most recent blog post
			'post' => $post,
			
			// the next few older posts after the most recent, offset by 1
			// only look for other posts if we have a first one
			'posts' => (
				$post === NULL
					? array()
					: $db->posts->getAllRecent(limit(10, 1))
			),
		);
	}
}
