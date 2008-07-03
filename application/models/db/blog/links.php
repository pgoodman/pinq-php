<?php

!defined('DIR_APPLICATION') && exit();

class LinksDefinition extends DatabaseModelDefinition {
	public function describe() {
		$this->id = int(10); // sqlite's own row id
		$this->url = string(255);
		$this->title = string(100);
		$this->description = text();
	}
}
