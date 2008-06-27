<?php

!defined('DIR_APPLICATION') && exit();

class LinksDefinition extends DatabaseModelDefinition {
	public function describe() {
		$this->id = int(3);
		$this->url = string(255);
		$this->title = string(50);
		$this->description = text();
	}
}