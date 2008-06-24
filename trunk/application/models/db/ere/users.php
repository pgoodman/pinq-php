<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->setInternalName('auth_Users');
		$this->Id = int(11);
		$this->Email = string(150);
		
		$this->Id->mapsTo('user_content_roles', 'UserId');
		$this->relatesTo('content', through('user_content_roles'));
	}
}