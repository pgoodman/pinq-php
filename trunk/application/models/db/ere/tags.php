<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	protected function describe() {
		
		return (struct('www_Tags')->
		    Id          ->int(11)->primary_key()->auto_increment(1)
		                ->mapTo('content_tags', 'TagId')->
		    Name        ->string(35)->

		    relatesTo('content', through('content_tags'))->
		    relatesTo('job_postings', through('content'))
		);
	}
}
