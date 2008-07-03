<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = int(10); // sqlite's own row id
		$this->email = string(150);
		$this->display_name = string(50);
		$this->password = string(32);
		
		$this->id->mapsTo('posts', 'user_id');
	}
}

class UsersRecord extends InnerRecord {
	public function __init__() {
		$this['display_id'] = base36_encode($this['id']);
		$this['perma_link'] = url('users', 'view', $this['display_id']);
	}
}
