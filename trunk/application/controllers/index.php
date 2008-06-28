<?php

/**
 * Iterate over job postings and find tags for a job.
 * @author Peter Goodman
 */
class JobPostingsIterator extends GatewayRecordIterator {	
	public function current() {
		$job = parent::current();
		$job->tags = $this->gateway->tags->getAll($job->content);
		return $job;
	}
}

/**
 * Index controller, this is where default actions come. Index controllers
 * are only really useful for having an index method.
 */
class IndexController extends PinqController {
	
	/**
	 * Main function for the index controller. Accepts a tag name from the
	 * route.
	 */
	public function GET_index($tag_name = '') {
		$db = $this->import('db.blog');
		
		$row = $db->get('select * from posts');
		print_r($row);
		/*
		// this query is used twice, yay!
		$post_query = from('posts')->select(ALL)->
		              from('users')->select(ALL)->
		              link('posts', 'users')->order()->posts('created')->desc;
		
		$this->view[] = array(
			
			// get the most recent blog post
			'post' => $db->get($post_query),
			
			// the next few older posts after the most recent, offset by 1
			'posts' => $db->getAll($post_query->limit(10, 1))
		);*/
	}
	
	/**
	 * Install the db tables.
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
