<?php

class IndexController extends Pinq_Controller {
	
	public function index() {
		
		$db = $this->import('db.ere');
		
		$result = $db->findAll(
			from('job_postings', 'jp')->select(ALL)->
		    from('tags')->link('jp', 'tags')->where->
		    tags('Name')->eq('accounting')->and->jp('id')->gt(0)
		);
		
		var_dump($result);
	}
}
