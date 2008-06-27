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
		
	}
	
	public function GET_archive() {
		
	}
}
