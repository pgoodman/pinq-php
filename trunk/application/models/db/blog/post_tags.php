<?php

!defined('DIR_APPLICATION') && exit();

class PostTagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->tag_id = int(5);
		$this->post_id = int(5);
		
		$this->post_id->mapsTo('posts', 'id');
		$this->tag_id->mapsTo('tags', 'id');
	}
}