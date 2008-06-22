<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		return (struct('auth_Users')->
		    Id          ->int(11)->primary_key()->auto_increment(1)
		                ->mapTo('user_content_roles', 'UserId')->
		    Email       ->string(150)->

		    relatesTo('content', through('user_content_roles'))
		);
	}
}