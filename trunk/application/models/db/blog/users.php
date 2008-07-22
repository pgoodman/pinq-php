<?php

!defined('DIR_APPLICATION') && exit();

class UsersDefinition extends PinqModelRelationalDefinition {
	
	public function describe() {
		
		$this->id = array(
			'type' => 'int',
			'optional' => TRUE,
		);
		
		$this->email = array(
			'type' => 'string',
			'max_length' => 100,
			'filter' => array('filter_email'),
		);
		$this->display_name = array(
			'type' => 'string',
			'length_between' => array(4, 20), 
			'regex' => '~^[a-zA-Z0-9_ .-]+$~',
		);
		$this->url = array(
			'type' => 'string',
			'optional' => TRUE,
			'max_length' => 50,
		);
		$this->password = array(
			'type' => 'string',
			'length_between' => array(1, 20),
			'filter' => array('md5_salted'),
			'max_length' => 32,
		);
		$this->login_key = array(
			'type' => 'string',
			'max_length' => 32,
			'optional' => TRUE,
		);
		
		// deal with relations
		$this->id->mapsTo('posts', 'user_id');
	}
}

class UsersGateway extends PinqModelRelationalGateway {
	public function register(Dictionary $post) {
		
		$errors = array();
		
		if($this->selectBy('email', $_POST['email'])) {
			$errors['email']['unique'] = (
				'Someone is already registered with this email.'
			);
		}
		
		$this->insert(into('users')->set($_POST)->errors($errors));
	}
}

class UsersRecord extends InnerRecord {
	public function __init__() {
		$this['perma_link'] = url('users', $this['display_id']);
	}
}
