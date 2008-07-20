<?php

/**
 * Install controller.
 */
class InstallResourceText extends PinqResourceText {
	
	/**
	 * Install the database tables.
	 */
	public function GET_index() {
		
		$this->db = $this->import('db.blog');
		
		// get the db tables from the schema file
		$schema = file_get_contents(DIR_APPLICATION .'/sqlite/blog.schema');
		$queries = explode(';', $schema);
		
		// create all of the database tables
		foreach($queries as $query) {
			
			if(empty($query))
				continue;
			
			//out('<pre>', $query, '</pre>');
			$this->db->insert($query);
		}
		
		$new_tag = into('tags')->set(array(
			'id' => NULL,
			'name' => _,
			'num_posts' => _,
		));
		$tag_link = into('post_tags')->set(array(
			'post_id' => _,
			'tag_id' => _,
		));
		
		$this->db->insert($new_tag, array('php', 2));
		$this->db->insert($new_tag, array('pinq', 2));
		$this->db->insert($new_tag, array('javascript', 0));
		$this->db->insert($new_tag, array('c', 0));
		$this->db->insert($new_tag, array('lisp', 0));
		$this->db->insert($new_tag, array('css', 0));
		$this->db->insert($new_tag, array('html', 0));
		$this->db->insert($new_tag, array('actionscript', 0));
		$this->db->insert($new_tag, array('flash', 0));
		$this->db->insert($new_tag, array('dev', 1));
		$this->db->insert($new_tag, array('code', 1));
		
		// add in our first post
		$this->db->insert(into('posts')->set(array(
			'id' => NULL,
			'title' => 'First blog post',
			'nice_title' => 'first-blog-post',
			'body' => str_repeat('This is the first blog post. ', 50),
			'user_id' => 1,
			'created' => time()-(60*60*24*7),
			'parent_id' => 0,
			'status' => 1,
		)));
		$this->db->insert($tag_link, array(1, 1));
		$this->db->insert($tag_link, array(1, 2));
		$this->db->insert($tag_link, array(1, 11));
		
		// add in our first post
		$this->db->insert(into('posts')->set(array(
			'id' => NULL,
			'title' => 'Second blog post',
			'nice_title' => 'second-blog-post',
			'body' => str_repeat('This is the second blog post. ', 25),
			'user_id' => 1,
			'created' => time()-(60*60*24),
			'parent_id' => 0,
			'status' => 1,
		)));
		
		$this->db->insert($tag_link, array(2, 1));
		$this->db->insert($tag_link, array(2, 2));
		$this->db->insert($tag_link, array(2, 10));
		
		$this->db->insert(into('posts')->set(array(
			'id' => NULL,
			'title' => 'Third blog post',
			'nice_title' => 'third-blog-post',
			'body' => str_repeat('lorum ipsum dolor sit amet. ', 25),
			'user_id' => 1,
			'created' => time(),
			'parent_id' => 0,
			'status' => 1,
		)));
		
		$this->db->insert($tag_link, array(3, 2));
		$this->db->insert($tag_link, array(3, 10));
		
		// add in our first user
		$this->db->insert(into('users')->set(array(
			'id' => NULL,
			'email' => 'peter.goodman@gmail.com',
			'display_name' => 'Peter Goodman',
			'password' => 'test',
		)));
		
		// done installing the blog
		echo 'installed';
	}
}
