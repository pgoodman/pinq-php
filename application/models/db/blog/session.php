<?php

!defined('DIR_APPLICATION') && exit();

class SessionDefinition extends DatabaseModelDefinition {
	public function describe() {
		$this->id = FieldType::string(array(
			'max_length' => 32,
		));
		$this->data = FieldType::text();
		$this->last_active = FieldType::int();
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