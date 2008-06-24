<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->setInternalName('www_Tags');
		
		$this->Id = int(11);
		$this->Name = string(35);
		
		$this->Id->mapsTo('content_tags', 'TagId');
		
		$this->relatesTo('content', through('content_tags'))
	         ->relatesTo('job_postings', through('content'))
	}
}
