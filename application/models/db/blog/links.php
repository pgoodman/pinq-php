<?php

!defined('DIR_APPLICATION') && exit();

class LinksDefinition extends DatabaseModelDefinition {
	public function describe() {
		$this->id = FieldType::int(10); // sqlite's own row id
		$this->url = FieldType::string(255);
		$this->title = FieldType::string(100);
		$this->description = FieldType::text();
	}
}
