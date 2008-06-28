<?php

/**
 * Index controller.
 */
class IndexController extends PinqController {
	
	/**
	 * Main function for the index controller.
	 */
	public function GET_index() {
		
		$db = $this->import('db.blog');
		
		// this query is used twice, yay!
		$post_query = from('posts')->select(ALL)->
		              from('users')->select(ALL)->
		              link('posts', 'users')->order()->posts('created')->desc;
		
		// set stuff to the view
		$this->view[] = array(
			// get the most recent blog post
			'post' => $db->get($post_query),
			
			// the next few older posts after the most recent, offset by 1
			'posts' => $db->getAll($post_query->limit(10, 1))
		);
	}
	
	/**
	 * Install the database tables.
	 */
	public function GET_install() {
		
		$db = $this->import('db.blog');
		
		$queries = explode(
			';', 
			file_get_contents(DIR_APPLICATION .'/sqlite/blog.schema')
		);
		
		foreach($queries as $query) {
			
			if(empty($query))
				continue;
			
			out('<pre>', $query, '</pre>');
			$db->post($query);
		}
		
		out('installed');
	}
}
