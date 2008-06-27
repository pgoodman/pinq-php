<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->name = string(20);
		$this->post_id = int(5);
		
		$this->post_id->mapsTo('posts', 'id');
	}
}