<?php

!defined('DIR_APPLICATION') && exit();

$config['blog'] = array(
	
	// where this session is stored
	'data_source' => 'db.blog',
	'model' => 'session',
	
	// model fields
	'field_id' => 'id',
	'field_data' => 'data',
	'field_time' => 'last_active',
);
