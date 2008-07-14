<?php

!defined('DIR_APPLICATION') && exit();

class TagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->id = array(
			'type' => 'int',
			'optional' => TRUE,
		);
		
		$this->name = array(
			'type' => 'string',
			'filter' => array(
				array($this, 'cleanTag'),
			),
			'length_between' => array(1, 15),
		);
		
		$this->num_posts = array('type' => 'int',);
		
		$this->relatesTo('posts', through('post_tags'));
	}
	
	public function cleanTag($tag) {
		return trim(preg_replace('~[^a-zA-Z0-9]+~', '', $tag), '-');
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