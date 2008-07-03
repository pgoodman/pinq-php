<?php

/**
 * Index controller.
 */
class IndexController extends AppController {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY_index() {
		
		$post = $this->db->posts->getNewest();
		/*
		$this->db->get(
			"SELECT tags.id AS tags_id, tags.name AS tags_name
			FROM tags, post_tags t1, posts 
			WHERE t1.post_id=posts.id
			AND tags.id=t1.tag_id 
			AND (posts.id = 1)"
		);*/
		
		// set stuff to the view
		$this->view[] = array(
			
			// get the most recent blog post
			'post' => $post,
			
			// the next few older posts after the most recent, offset by 1
			// only look for other posts if we have a first one
			'posts' => (
				$post === NULL
					? array()
					: $this->db->posts->getAll(limit(10, 1))
			),
		);
	}
}
