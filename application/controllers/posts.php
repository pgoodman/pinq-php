<?php

/**
 * Posts controller.
 */
class PostsController extends PinqController {
	
	public function GET_view($nice_title = '') {
		
		$db = $this->import('db.blog');
		
		// get the post and if it doesn't exist, error
		if(!($post = $db->posts->getBy('nice_title', $nice_title)))
			yield(ERROR_404);
		
		$this->view['post'] = $post;
	}
}
