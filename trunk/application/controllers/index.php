<?php

class IndexController extends PinqController {
    
    /**
     * Main function for the index controller. Accepts a tag name from the
     * route.
     */
    public function index($tag_name = '') {
        
        $db = $this->import('db.ere');
        
        // find all jobs including with their content information by a
        // specific tag name passed in through the route
        $jobs = $db->findAll(
            from('job_postings', 'jp')->select(ALL)->
            from('content')->select(ALL)->link('jp', 'content')->
            from('tags')->link('jp', 'tags')->where->tags('Name')->eq(_),
            
            array($tag_name) // substitute into the query
        );
        
        // iterate over the jobs and output html for them
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
