<?php

/**
 * Install controller.
 */
class InstallController extends PinqController {
	
	/**
	 * Install the database tables.
	 */
	public function GET_index() {
		
		$db = $this->import('db.blog');
		
		// get the db tables from the schema file
		$queries = explode(
			';', 
			file_get_contents(DIR_APPLICATION .'/sqlite/blog.schema')
		);
		
		// create all of the database tables
		foreach($queries as $query) {
			
			if(empty($query))
				continue;
			
			out('<pre>', $query, '</pre>');
			$db->post($query);
		}
		
		// add in our first post
		$db->post(to('posts')->set(array(
			'id' => NULL,
			'title' => 'First blog post',
			'nice_title' => 'first-blog-post',
			'body' => str_repeat('This is the first blog post. ', 50),
			'user_id' => 1,
			'created' => time(),
			'published' => TRUE,
		)));
		
		// add in our first user
		$db->post(to('users')->set(array(
			'id' => NULL,
			'email' => 'peter.goodman@gmail.com',
			'display_name' => 'Peter Goodman',
			'password' => md5('test'),
		)));
		
		// done installing the blog
		out('installed');
	}
}
