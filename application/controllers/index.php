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
        $jobs = $db->findAll(
        
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
        );
	    
        // go into the model gateway with a partial query for the job postings
        // model. The $db->job_postings is equivalent to the abstract query:
        // from('job_postings')->select(ALL)
        // put in the wrapper of a model-gateway
        /*$featured = $db->job_postings->findBy('Id', 1);
        
        echo '<pre>';
        print_r($featured);
        echo '</pre>';
		*/
		
        // iterate over the jobs and output html for them. this would
        // eventually be moved to some sort of view
        foreach($jobs as $job) {
            outln(
                '<h3>'. $job['Title'] .'</h3>',
                '<hr />',
                '<div>',
                $job['ContentHtml'],
                '</div>'
            );
        }
        
        // all done :D
    }
}
