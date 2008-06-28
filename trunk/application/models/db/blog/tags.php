<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = int(10); // sqlite's own row id
		$this->name = string(20);
		
		$this->relatesTo('posts', through('post_tags'));
	}
}