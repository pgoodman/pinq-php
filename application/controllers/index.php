<?php

class IndexController extends PinqController {
	
	/**
	 * Main function for the index controller. Accepts a tag name from the
	 * route.
	 */
	public function GET_index($tag_name = '') {
				
		// import the ere database and its associated models
		$db = $this->import('db.ere');
		
		// find all job postings with their content by a given tag name
		$jobs = from('job_postings', 'jp')->select(ALL)->
				from('content', 'c')->select(ALL)->
				link('jp', 'c')->
				from('tags', 't')->link('jp', 't')->
				where()->t('Name')->eq->_->limit(5)->order()->jp('Id')->desc;
		
		// iterate over the jobs and output html for them. this would
		// eventually be moved to some sort of view
		foreach($db->getAll($jobs, array($tag_name)) as $job) {
			
			// custom method in JobPostingsRecord
			$job->job_postings->sayHi();
			
			// output the job posting content. The fields being accessed in
			// here are actually ambiguous and are resolved to one of the
			// interior records of $job
			outln(
				'<h3>', 
					$job['Title'], 
					':', $job->job_postings['Id'], 
					':', $job->content['Id'],
				'</h3>',
				'<hr />',
				'<div>',
				strip_tags($job['ContentHtml']),
				'</div>',
				'<strong>Tags:</strong>',
				'<ul>'
			);
			
			// the way this query works is it says: get tags using the data
			// from $job->job_postings by satisying any relationships between
			// the two tables.
			foreach($db->tags->getAll($job->content) as $tag)
				out('<li>', $tag['Name'], '</li>');
			
			out('</ul>');
		}
	}
	
	public function GET_moo() {
		out('in moo!!!');
	}
}
