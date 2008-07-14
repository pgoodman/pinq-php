<?php

/**
 * Index controller.
 */
class IndexResource extends AppResource {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY_index() {
		
		$posts = $this->db->posts->getAll(limit(11));
		
		$view = array(
			'post' => NULL,
			'posts' => NULL,
			'tags' => NULL,
		);
		
		if(count($posts)) {
			$view['post'] = $posts->shift();
			$view['posts'] = $posts;
			$view['tags'] = $this->db->tags->getAll($view['post']->posts);
		}
		
		$this->layout['title'] = 'Curious About Programming';
		$this->view[] = $view;
	}
	
	/**
	 * View the source for a particular route's controller.
	 */
	public function ANY_source() {
		$router = $this->import('route-parser');
		
		// make sure a route was passed and if so parse it
		if(!isset($_GET['route']) || !($path = $router->parse($_GET['route'])))
			yield(ERROR_404);
		
		// highlight the file
		$this->view['source'] = highlight_file(
			$path[0] .'/'. $path[2] . EXT,
			TRUE
		);
		
		// send the original route to the layout view
		$this->view['original_route'] = $_GET['route'];
		$this->layout['original_route'] = $_GET['route'];
		$this->layout['title'] = 'View Source';
	}
	
	public function ANY_dump($table) {
		//$this->db->delete(from($table));
		$rows = $this->db->getAll(from($table)->select(ALL));
		foreach($rows as $row) {
			echo '<pre>';
			print_r($row);
			echo '</pre>';
		}
		
		out(count($rows), 'rows');
	}
	
	/**
	 * About page.
	 */
	public function GET_about() {
		$this->layout['title'] = 'About '. $this->layout['blog_author'];
	}
}