<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = FieldType::int(10); // sqlite's own row id
		$this->email = FieldType::string(150);
		$this->display_name = FieldType::string(50);
		$this->url = FieldType::string(50);
		$this->password = FieldType::string(32);
		$this->login_key = FieldType::string(32);
		
		$this->id->mapsTo('posts', 'user_id');
	}
}

class UsersRecord extends InnerRecord {
	public function __init__() {
		$this['display_id'] = base36_encode($this['id']);
		$this['perma_link'] = url('users', $this['display_id']);
	}
}
