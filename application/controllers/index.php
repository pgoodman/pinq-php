<?php

class IndexController extends Pinq_Controller {
	
	public function index() {
		
		$db = $this->import('db.wordpress');
		
		$result = $db->findAll(from('categories', 'c')->select(ALL)->
		                       from('categories', 'p')->link('c', 'p'));
		
		// SELECT c.* FROM   (categories c  INNER JOIN categories p ON c.category_parent=p.cat_ID)
		
		var_dump($result);
	}
}
