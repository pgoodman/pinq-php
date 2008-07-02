<?php

/**
 * Index controller.
 */
class IndexController extends PinqController {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY_index() {
		
		help('ModelGateway');
				
		$db = $this->import('db.blog');
		
		out('num rows:', (int)$db->getValue(from('posts')->count('id')));
		
		// this query is used twice, yay! find the current published posts
		// ordered by their created time (descending)
		$post_query = from('posts')->select(ALL)->
		              from('users')->select(ALL)->
		              link('posts', 'users')->
		              where()->posts('published')->is(TRUE)->
		              order()->posts('created')->desc;
		
		$post = $db->get($post_query);
		
		// set stuff to the view
		$this->view[] = array(
			
			// get the most recent blog post
			'post' => $post,
			
			// the next few older posts after the most recent, offset by 1
			// only look for other posts if we have a first one
			'posts' => ($post === NULL
				? array()
				: $db->getAll($post_query->limit(10, 1))
			),
		);
	}
}
