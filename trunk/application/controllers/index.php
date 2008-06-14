<?php

class IndexController extends Pinq_Controller {
	
	public function index() {
		
		$db = $this->import('db.wordpress');
		
		$result = $db->findAll(from('categories', 'c')->select(ALL)->
		                       from('categories', 'p')->link('c', 'p'));
		
		// compiles this query:
		// SELECT c.* FROM (wp_categories c INNER JOIN wp_categories p ON c.category_parent=p.cat_ID)
		
		var_dump($result);
	}
}
