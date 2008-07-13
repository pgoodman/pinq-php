<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->id = FieldType::int(array(
			'optional' => TRUE,
		));
		
		$this->email = FieldType::string(array(
			'max_length' => 100,
			//'regex' => REGEX_EMAIL,
		));
		$this->display_name = FieldType::string(array(
			'length_between' => array(4, 20), 
			'regex' => '~^[a-zA-Z0-9_ .-]+$~',
		));
		$this->url = FieldType::string(array(
			'optional' => TRUE,
			'max_length' => 50,
			//'regex' => REGEX_URL,
		));
		$this->password = FieldType::string(array(
			'filter' => 'md5_salted',
			'max_length' => 32,
		));
		$this->login_key = FieldType::string(array(
			'max_length' => 32,
			'optional' => TRUE,
		));
		
		// deal with relations
		$this->id->mapsTo('posts', 'user_id');
	}
}

class UsersGateway extends DatabaseModelGateway {
	public function register(Dictionary $post) {
		
		$errors = array();
		
		if($this->getBy('email', $_POST['email'])) {
			$errors['email']['unique'] = (
				'Someone is already registered with this email.'
			);
		}
		
		$this->insert(to('users')->set($_POST)->errors($errors));
	}
}

class UsersRecord extends DatabaseRecord {
	public function __init__() {
		$this['display_id'] = base36_encode($this['id']);
		$this['perma_link'] = url('users', $this['display_id']);
	}
}
