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

class PostsRecord extends DatabaseRecord {
    public function __init__() {
        $this['display_id'] = base36_encode($this['id']);
        $this['perma_link'] = url(date("Y/m/d"), $this['nice_title']);
    }
}

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
            where()->posts('published')->is(TRUE)
        );
    }
    
    /**
     * $p->getNewest(void) -> Record
     *
     * Return the newest blog post.
     */
    public function getNewest() {
        return parent::get($this->getPartialQuery());
    }
    
    /**
     * $p->getRecent([QueryPredicates]) -> RecordIterator
     *
     * Get recent blog posts.
     */
    public function getAllRecent(QueryPredicates $predicates = NULL) {
        return $this->getAll(order()->posts('id')->desc->merge($predicates));
    }
}