<?php

!defined('DIR_APPLICATION') && exit();

class PostsDefinition extends DatabaseModelDefinition {
	public function describe() {
		
		$this->id = int(10); // sqlite's own row id
		$this->title = string(100);
		$this->body = text();
		$this->user_id = int(5);
		$this->nice_title = string(100);
		$this->created = int(10);
		$this->published = bool();
		
		$this->user_id->mapsTo('users', 'id');
		
		$this->relatesTo('tags', through('post_tags'));
	}
}

class PostsRecord extends InnerRecord {
	public function __init__() {
		$this['display_id'] = base36_encode($this['id']);
		$this['perma_link'] = url(date("Y/m/d"), $this['nice_title']);
	}
}
