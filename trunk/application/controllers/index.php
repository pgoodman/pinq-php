<?php

class IndexController extends Pinq_Controller {
    
    public function index() {
        
        $db = $this->import('db.ere');
        
		// find all jobs, with their content information, 
        $jobs = $db->findAll(
            from('job_postings', 'jp')->select(ALL)->
			from('content')->select(ALL)->link('jp', 'content')->
            from('tags')->link('jp', 'tags')->where->tags('Name')->eq(_),
			array('accounting') // substitute into the query
        );
		
        foreach($jobs as $job) {
            echo '<br>'. $job['Id'];
        }
    }
}
