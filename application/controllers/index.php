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
				
		// import the ere database and its associated models
		$db = $this->import('db.ere');
		
		// find all job postings with their content by a given tag name
		$query = from('job_postings', 'jp')->select(ALL)->
				from('content', 'c')->select(ALL)->
				link('jp', 'c')->
				from('tags', 't')->link('jp', 't')->
				where()->t('Name')->eq->_->limit(5)->order()->jp('Id')->desc;
		
		// send some variables to the view
		$this->view['jobs'] = new JobPostingsIterator(
			$db->getAll($query, array($tag_name)),
			$db
		);
	}
}
