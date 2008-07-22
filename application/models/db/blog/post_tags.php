<?php

!defined('DIR_APPLICATION') && exit();

class PostTagsDefinition extends PinqModelRelationalDefinition {
	
	public function describe() {
		$this->tag_id = array('type' => 'int');
		$this->post_id = array('type' => 'int');
		
		$this->post_id->mapsTo('posts', 'id');
		$this->tag_id->mapsTo('tags', 'id');
	}
}