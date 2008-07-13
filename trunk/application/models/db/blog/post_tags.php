<?php

!defined('DIR_APPLICATION') && exit();

class PostTagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->tag_id = FieldType::int();
		$this->post_id = FieldType::int();
		
		$this->post_id->mapsTo('posts', 'id');
		$this->tag_id->mapsTo('tags', 'id');
	}
}