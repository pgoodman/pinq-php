<?php

!defined('DIR_APPLICATION') && exit();

class JobPostingsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->setInternalName('jobs_JobPostings');
		
		$this->Id = int(11);
		$this->ContentId = int(11);
		$this->Instructions = text();
		$this->EmployerName = string(100);
		$this->ClickThroughUrl = string(150);
		
		$this->ContentId->mapsTo('content', 'Id');
		
		$this->relatesTo('users', through('user_content_roles'))
	         ->relatesTo('tags', through('content'));
	}
}

class JobPostingsRecord extends InnerRecord {
	public function sayHi() {
		out('hi');
	}
}
