<?php

!defined('DIR_APPLICATION') && exit();

class UserContentRolesDefinition extends DatabaseModelDefinition {
	public function describe() {
		
		$this->setInternalName('www_UserContentRoles');
		
		$this->UserId = int(11);
		$this->ContentId = int(11);
		
		$this->UserId->mapsTo('users', 'Id');
		$this->ContentId->mapsTo('content', 'Id');
	}
}
