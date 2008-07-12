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
	/*
	protected $_validate = array(
		'email' => V::UNIQUE | V::EMAIL,
		'display_name' => V::LENGTH(5, 20) | V::REGEX('^[a-zA-Z0-9_- ]+$'),
		'url' => V::OPTIONAL | V::URL,
		'password' => V::LENGTH(5, 20) | V::ANY,
	);*/
	/*
	public function validateFields(array $fields) {
		$fields = parent::validateFields($fields);
		
		
		
		return $fields;
	}*/
}

class UsersGateway extends DatabaseModelGateway {
	public function insert($query, array $args = array()) {
		
		if(!is_array($query)) {
			return parent::insert($query, $args);
		}
		
		$errors = array();
		if(NULL === $this->getBy('email', $_POST['email']))
			$errors['email'] = "";
	}
}

class UsersRecord extends InnerRecord {
	public function __init__() {
		$this['display_id'] = base36_encode($this['id']);
		$this['perma_link'] = url('users', $this['display_id']);
	}
}
