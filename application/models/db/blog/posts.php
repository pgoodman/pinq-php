<?php

!defined('DIR_APPLICATION') && exit();

/**
 * Class describing the fields of the 'posts' table in the database.
 *
 * @author Peter Goodman
 */
class PostsDefinition extends PinqModelRelationalDefinition {
	
	const DRAFT = 0,
	      PUBLISHED = 1,
	      SPAM = 2;
	
	public function describe() {
		
		$this->id = array(
			'type' => 'int',
			'optional' => TRUE,
		);
		
		$this->title = array(
			'type' => 'string',
			'max_byte_len' => 150,
			'default' => NULL,
		);
		
		$this->body = array('type' => 'string');
		
		$this->user_id = array('type' => 'int');
		
		$this->nice_title = array(
			'type' => 'string',
			'filter' => array(
				array($this, 'cleanTitle')
			),
			'max_byte_length' => 100,
			'min_byte_length' => 5,
		);
		
		$this->created = array('type' => 'int',);
		
		$this->status = array(
			'type' => 'int',
			'default' => array(
				self::DRAFT, 
				self::PUBLISHED, 
				self::SPAM
			),
		);
		$this->parent_id = array('type' => 'int',);
		$this->num_children = array('type' => 'int',);
		
		// relations
		$this->user_id->mapsTo('users', 'id');
		$this->parent_id->mapsTo('posts', 'id');
		$this->relatesTo('tags', through('post_tags'));
	}

	public function cleanTitle($title) {
		return preg_replace('~[^a-zA-Z0-9]+~', '-', $title);
	}
}

/**
 * Class representing a single 'post' record from the database.
 *
 * @author Peter Goodman
 */
class PostsRecord extends InnerRecord {
	
	/**
	 * Constructor hook.
	 */
	public function __init__() {

		// make a readable representation of this post id
		$this['display_id'] = base36_encode($this['id']);
		$this['display_created'] = date("F j, Y, g:ia", $this['created']);
		
		// create a nice url to access this post
		$this['perma_link'] = url(
			date("Y/m/d", $this['created']), 
			$this['nice_title']
		);
	}
}

/**
 * Class representing a way to directly access records from the 'posts' table
 * in the database.
 *
 * @author Peter Goodman
 */
class PostsGateway extends PinqDbModelRelationalGateway {
	
	/**
	 * Extend the partial query of the model gateway. This lets us do some
	 * awesome magic.
	 */
	public function createPqlQuery() {
		return (
			parent::createPqlQuery()->
			from('users', 'u')->select('id', 'display_name')->
			link('posts', 'u')->
			order()->posts('created')->desc->
			where()->posts('status')->is(PostsDefinition::PUBLISHED)
		);
	}
}
