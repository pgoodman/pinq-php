<?php

class IndexController extends PinqController {
    
    /**
     * Main function for the index controller. Accepts a tag name from the
     * route.
     */
    public function index($tag_name = '') {
	
		$time_start = list($sm, $ss) = explode(' ', microtime());
        
        // import the ere database and its associated models
        $db = $this->import('db.ere');
		
        // find all jobs including with their content information by a
        // specific tag name passed in through the route
        $jobs = $db->findAll(
        
            // get all columns from the job posting table
            from('job_postings', 'jp')->select(ALL)->

            // get all columns from 4the content table and link it to the job
            // postings table automatically
            from('content')->select(ALL)->link('jp', 'content')->

            // link tags to job postings with an implicit through join
            from('tags')->link('jp', 'tags')->where()->tags('Name')->eq(_)->
			limit(3),

            // substitute into the query for the tag name
            array($tag_name)
        );

		
        // iterate over the jobs and output html for them. this would
        // eventually be moved to some sort of view
        foreach($jobs as $job) {
						
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
			foreach($db->tags->findAll($job->job_postings) as $tag)
				out('<li>', $tag['Name'], '</li>');
			
			out('</ul>');
        }

		$time_end = list($em, $es) = explode(' ', microtime());
		out('<pre>', 'Compile & query time:', ($em + $es) - ($sm + $ss), '</pre>');
        
        // all done :D
    }
}
