<?php

!defined('DIR_APPLICATION') && exit();

class UserContentRolesDefinition extends DatabaseModelDefinition {
	public function describe() {
		
		return (struct('www_UserContentRoles')->
		    Id             ->int(11)->primary_key()->auto_increment(1)->
		    UserId         ->int(11)->mapTo('users', 'Id')->
		    ContentId      ->int(11)->mapTo('content', 'Id')
		);
	}
}
