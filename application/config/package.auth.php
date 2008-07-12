<?php

!defined('DIR_APPLICATION') && exit();

$config['blog'] = array(
	
	// the data source where user information is stored
	'data_source' => 'db.blog',
	
	// the session to store user state
	'session' => 'session.blog',
		
	// the model from the data source where user information is represented
	'model' => 'users',

	// php callback for a hash function to hash the user's password when
	// logging in
	'hash_function' => 'md5_salted',

	// the unique user ID
	'field_user_id' => 'id',
	
	// user login/pass
	'field_login' => 'email',
	'field_pass' => 'password',
	
	// a key to identify someone for auto-logging in. This key is changed each
	// time a user logs in and is stored in a long-lived cookie. Leave blank
	// to entirely disable auto logging in.
	'field_login_key' => 'login_key',
	
	// the number of days to keep a user auto logged in
	'auto_login_days' => 10,
);
