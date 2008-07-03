<?php

/**
 * Posts controller.
 */
class PostsController extends AppController {
	
	public function GET_view($nice_title = '') {
		
		$db = $this->db;
		
		// get the post and if it doesn't exist, error. $post is actually a
		// multi-record with posts and users record info in it
		if(!($post = $db->posts->getBy('nice_title', $nice_title)))
			yield(ERROR_404);
		
		// set data to the view
		$this->view[] = array(
			'post' => $post,
			'tags' => $db->tags->getAll($post->posts),
		);
	}
}
