<?php

/**
 * Index controller.
 */
class IndexController extends AppController {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY_index() {
		
		$post = $this->db->posts->getNewest();
		
		$view = array(
			'post' => NULL,
			'posts' => NULL,
			'tags' => NULL,
		);
		
		if($post) {
			$view['post'] = $post;
			$view['posts'] = $this->db->posts->getAll(limit(10, 1));
			$view['tags'] = $this->db->tags->getAll($post->posts);
		}
		
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
		$this->layout['original_route'] = $_GET['route'];
	}
}
