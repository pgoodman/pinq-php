<?php

!defined('DIR_APPLICATION') && exit();

class PostsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = int(5);
		$this->title = string(255);
		$this->body = text();
		$this->user_id = int(5);
		
		$this->user_id->mapsTo('users', 'id');
		$this->id->mapsTo('tags', 'post_id');
	}
}
