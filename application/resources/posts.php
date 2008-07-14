<?php

/**
 * Posts controller.
 */
class PostsResource extends AppResource {
	
	/**
	 * View a single post and its comments.
	 */
	public function GET_index($nice_title = '') {
		
		// get the post and if it doesn't exist or is a comment then error
		$post = $this->db->posts->getBy('nice_title', $nice_title);
		
		if(!$post || $post['parent_id'] > 0)
			yield(ERROR_404);
		
		// get the comments
		$comments = NULL;
		if($post['num_children'] > 0)
			$comments = $this->db->posts->getAll($post->posts);
		
		// set data to the view
		$this->layout['title'] = $post['title'];
		$this->view[] = array(
			'post' => $post,
			'tags' => $this->db->tags->getAll($post->posts),
			'comments' => $comments,
		);
	}
	
	/**
	 * Add a new comment.
	 */
	public function POST_index($parent_id = 0) {
		
		$parent_id = (int)base36_decode($parent_id);
		if(NULL === $this->db->posts->getBy('id', $parent_id))
			$parent_id = 0;
		
		// people aren't allowed to post top-level posts
		if($parent_id === 0)
			yield(ERROR_401);
		
		
	}
}
