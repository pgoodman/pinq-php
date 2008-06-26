<?php

!defined('DIR_APPLICATION') && exit();

class ContentDefinition extends DatabaseModelDefinition {
	public function describe() {
		
		$this->setInternalName('www_Content');
		
		// define the fields
		$this->Id = int(11);
		$this->Title = string(75);
		$this->ContentHtml = text();
		
		// add some mappings, these automatically add in direct relationships
		$this->Id->mapsTo('job_postings', 'ContentId')
		         ->mapsTo('content_tags', 'ContentId');
		
		// add in some indirect relationships
		$this->relatesTo('tags', through('content_tags'))
		     ->relatesTo('users', through('user_content_roles'));
	}
}
