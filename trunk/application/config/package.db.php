<?php

!defined('DIR_APPLICATION') && exit();

$config['wordpress'] = array(
	'user' => 'root',
	'pass' => 'root',
	'host' => 'localhost',
	'name' => 'ere_wp',
	'driver' => 'mysql',
	'port' => 80,
);

$config['ere'] = array(
	'user' => 'root',
	'pass' => 'root',
	'host' => 'localhost',
	'name' => 'ere_jobs',
	'driver' => 'mysql',
	'port' => 80,
);
