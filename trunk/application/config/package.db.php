<?php

!defined('DIR_APPLICATION') && exit();

$config['blog'] = array(
	'user' => '',
	'pass' => '',
	'host' => realpath(DIR_APPLICATION .'/sqlite/blog.sqlite'),
	'name' => '',
	'driver' => 'sqlite',
	'port' => 0,
);
