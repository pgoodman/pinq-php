<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = int(5);
		$this->email = string(150);
		$this->display_name = string(50);
		
		$this->id->mapsTo('posts', 'user_id');
	}
}