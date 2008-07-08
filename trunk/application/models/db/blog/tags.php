<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		$this->id = FieldType::int(10); // sqlite's own row id
		$this->name = FieldType::string(20);
		$this->num_posts = FieldType::int(5);
		
		$this->relatesTo('posts', through('post_tags'));
	}
}

class TagsGateway extends DatabaseModelGateway {
	
	public function getPopular() {
		return $this->getAll(
			$this->getPartialQuery()->
			       order()->num_posts->desc->
			       limit(6)
		);
	}
}

class TagsRecord extends DatabaseRecord {
	public function __init__() {
		$this['perma_link'] = url('tags', $this['name']);
	}
}