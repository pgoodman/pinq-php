<?php

!defined('DIR_APPLICATION') && exit();

class JobPostingsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		return (struct('jobs_JobPostings')->
		    Id                ->int(11)->primary_key()->auto_increment(1)->
		    ContentId         ->int(11)
		                      ->mapTo('content', 'Id')->
		    Instructions      ->string()->
		    EmployerName      ->string(100)->
		    ClickThroughUrl   ->string(150)->

		    relatesTo('users', through('user_content_roles'))->
		    relatesTo('tags', through('content'))
		);
	}
}