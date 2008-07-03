<?php

/**
 * Posts controller.
 */
class PostsController extends AppController {
	
	/**
	 * View a single post and its comments.
	 */
	public function GET_view($nice_title = '') {
		
		$db = $this->db;
		
		// get the post and if it doesn't exist or is a comment then error
		$post = $db->posts->getBy('nice_title', $nice_title);
		
		if(!$post || $post['parent_id'] > 0)
			yield(ERROR_404);
		
		// set data to the view
		$this->view[] = array(
			'post' => $post,
			'tags' => $db->tags->getAll($post->posts),
			'comments' => $db->posts->getAll($post->posts),
		);
	}
}
