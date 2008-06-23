<?php

!defined('DIR_APPLICATION') && exit();

class ContentDefinition extends DatabaseModelDefinition {
	
	protected function describe() {
		return (struct('www_Content')->
		    Id           ->int(11)->primary_key()->auto_increment(1)
		                 ->mapTo('job_postings', 'ContentId')
		                 ->mapTo('content_tags', 'ContentId')->
		    Title        ->string(75)->
		    ContentHtml  ->string()->

		    relatesTo('tags', through('content_tags'))->
		    relatesTo('users', through('user_content_roles'))
		);
	}
}
