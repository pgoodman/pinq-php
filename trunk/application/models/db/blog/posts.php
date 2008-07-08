<?php

!defined('DIR_APPLICATION') && exit();

/**
 * Class describing the fields of the 'posts' table in the database.
 *
 * @author Peter Goodman
 */
class PostsDefinition extends DatabaseModelDefinition {
	
	const DRAFT = 0,
	      PUBLISHED = 1,
	      SPAM = 2;
	
    public function describe() {
        
        $this->id = FieldType::int(10); // sqlite's own row id
        $this->title = FieldType::string(100);
        $this->body = FieldType::text();
        $this->user_id = FieldType::int(5);
        $this->nice_title = FieldType::string(100);
        $this->created = FieldType::int(10);
        $this->status = FieldType::enum(
			self::DRAFT, 
			self::PUBLISHED, 
			self::SPAM
		);
		$this->parent_id = FieldType::int(10);
		$this->num_children = FieldType::int(5);
        
        $this->user_id->mapsTo('users', 'id');
		$this->parent_id->mapsTo('posts', 'id');
        
        $this->relatesTo('tags', through('post_tags'));
    }
}

/**
 * Class representing a single 'post' record from the database.
 *
 * @author Peter Goodman
 */
class PostsRecord extends DatabaseRecord {
	
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
class PostsGateway extends DatabaseModelGateway {
    
    /**
     * Extend the partial query of the model gateway. This lets us do some
     * awesome magic.
     */
	public function getPartialQuery() {
		return (
			parent::getPartialQuery()->
			from('users')->select(ALL)->
			link('posts', 'users')->
			order()->posts('created')->desc->
			where()->posts('status')->is(PostsDefinition::PUBLISHED)
		);
    }
    
    /**
     * $p->getNewest(void) -> Record
     *
     * Return the newest blog post.
     */
	public function getNewest() {
		return $this->get($this->getPartialQuery());
	}
}
