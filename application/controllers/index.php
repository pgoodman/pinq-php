<?php

class IndexController extends PinqController {
    
    /**
     * Main function for the index controller. Accepts a tag name from the
     * route.
     */
    public function index($tag_name = '') {
	        
        // import the ere database and its associated models
        $db = $this->import('db.ere');
		
		$result = $db->modify(
			in('job_postings', 'jp')->set(array(
				'EmployerName' => 'ERE.net',
				'Instructions' => 'Go east!',
				'ClickThroughUrl' => 'http://ere.net',
			))->
	
			in('content')->set(array(
				'Title' => 'The title of this content',
				'ContentHtml' => 'The html of this job posting',
			))->
	
			link('jp', 'content')->
			where()->jp('Id')->eq(1134)
		);
		
		var_dump($result);
		
		// find all job postings with their content by a given tag name
		$jobs = from('job_postings', 'jp')->select(ALL)->
		        from('content', 'c')->select(ALL)->
		        link('jp', 'c')->
		        from('tags', 't')->link('jp', 't')->
		        where()->t('Name')->eq->_->limit(5)->order()->jp('Id')->desc;
				
        // iterate over the jobs and output html for them. this would
        // eventually be moved to some sort of view
        foreach($db->findAll($jobs, array($tag_name)) as $job) {
						
			// output the job posting content. The fields being accessed in
			// here are actually ambiguous and are resolved to one of the
			// interior records of $job
            outln(
                '<h3>'. $job['Title'] .'</h3>',
                '<hr />',
                '<div>',
                strip_tags($job['ContentHtml']),
                '</div>',
				'<strong>Tags:</strong>',
				'<ul>'
            );
			
			// output the tags. the tags need to be found using
			// $job->job_postings because $job is an ambiguous record, meaning
			// it is actually two records in one.
			//
			// the way this query works is it says: get tags using the data
			// from $job->job_postings by satisying any relationships between
			// the two tables.
			foreach($db->tags->findAll($job->content) as $tag)
				out('<li>', $tag['Name'], '</li>');
			
			out('</ul>');
        }

        // all done :D
    }
}
