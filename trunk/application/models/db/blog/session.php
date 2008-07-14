<?php

!defined('DIR_APPLICATION') && exit();

class SessionDefinition extends DatabaseModelDefinition {
	public function describe() {
		$this->id = array(
			'type' => 'string',
			'max_length' => 32,
		);
		$this->data = array('type' => 'string',);
		$this->last_active = array('type' => 'int',);
	}
}

class SessionRecord extends DatabaseRecord {
	public function __init__() {
		
		if(NULL === $this['data'])		
			$this['data'] = array();
		
		else if(is_string($this['data']))
			$this['data'] = unserialize($this['data']);
	}
}