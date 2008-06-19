<?php

class IndexController extends PinqController {
    
    /**
     * Main function for the index controller. Accepts a tag name from the
     * route.
     */
    public function index($tag_name = '') {
        
        // import the ere database and its associated models
        $db = $this->import('db.ere');
        
        // find all jobs including with their content information by a
        // specific tag name passed in through the route
        /*$jobs = $db->findAll(
        
            // get all columns from the job posting table
            from('job_postings', 'jp')->select(ALL)->

            // get all columns from 4the content table and link it to the job
            // postings table automatically
            from('content')->select(ALL)->link('jp', 'content')->

            // link tags to job postings with an implicit through join
            from('tags')->link('jp', 'tags')->where->tags('Name')->eq(_)->
			limit(5),

            // substitute into the query for the tag name
            array($tag_name)
        );*/
		
		$tags_gateway = $db->tags;
		
		// find all jobs
		foreach($db->job_postings->findAll(limit(2)) as $job) {
			print_r($job);
			
			$tags = $tags_gateway->findAll($job);
			
			var_dump($tags);
		}
		
		// find al jobs by a tag	
		
		
        // iterate over the jobs and output html for them. this would
        // eventually be moved to some sort of view
        /*foreach($jobs as $job) {
						
			// output the job posting content
            outln(
                '<h3>'. $job['Title'] .'</h3>',
                '<hr />',
                '<div>',
                $job['ContentHtml'],
                '</div>'
            );
			
			// output the tags
			$sep = '';
			foreach($db->findAll($job->content->tags) as $tag) {
				out('<span>', $tag['Name'], '</span>', $sep);
				$sep = ',';
			}
        }*/
        
        // all done :D
    }
}
