<?php

!defined('DIR_APPLICATION') && exit();

class ContentTagsDefinition extends DatabaseModelDefinition {
	
	protected function describe() {
		return (struct('www_ContentTags')->
		    Id           ->int(11)->primary_key()->auto_increment(1)->
		    ContentId    ->int(11)->mapTo('content', 'Id')->
		    TagId        ->int(11)->mapTo('tags', 'Id')
		);
	}
}
