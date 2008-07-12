<?php

!defined('DIR_APPLICATION') && exit();

$config['blog'] = array(
	
	// which session will this history stack be stored in?
	'session' => 'session.blog',
	
	// the number of urls to store in the stack
	'max_urls' => 5,
);
