<?php

/**
 * Install controller.
 */
class InstallController extends AppController {
	
	/**
	 * Install the database tables.
	 */
	public function GET_index() {
		
		// get the db tables from the schema file
		$schema = file_get_contents(DIR_APPLICATION .'/sqlite/blog.schema');
		$queries = explode(';', $schema);
		
		// create all of the database tables
		foreach($queries as $query) {
			
			if(empty($query))
				continue;
			
			out('<pre>', $query, '</pre>');
			$db->post($query);
		}
		
		// add in our first post
		$this->db->post(to('posts')->set(array(
			'id' => NULL,
			'title' => 'First blog post',
			'nice_title' => 'first-blog-post',
			'body' => str_repeat('This is the first blog post. ', 50),
			'user_id' => 1,
			'created' => time()-(60*60*24*7),
			'published' => TRUE,
		)));
		
		// add in our first post
		$this->db->post(to('posts')->set(array(
			'id' => NULL,
			'title' => 'Second blog post',
			'nice_title' => 'second-blog-post',
			'body' => str_repeat('This is the second blog post. ', 25),
			'user_id' => 1,
			'created' => time(),
			'published' => TRUE,
		)));
		
		// add in our first user
		$this->db->post(to('users')->set(array(
			'id' => NULL,
			'email' => 'peter.goodman@gmail.com',
			'display_name' => 'Peter Goodman',
			'password' => md5('test'),
		)));
		
		// done installing the blog
		out('installed');
	}
}
