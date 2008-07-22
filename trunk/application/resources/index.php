<?php

/**
 * Index controller.
 */
class IndexResource extends AppResource {
	
	/**
	 * Main function for the index controller.
	 */
	public function ANY() {
		
		$posts = $this->db->posts->selectAll(limit(11));
		
		echo help($posts);
		
		$view = array(
			'post' => NULL,
			'posts' => NULL,
			'tags' => NULL,
		);
		
		if(count($posts)) {
			$view['post'] = $posts->shift();
			$view['posts'] = $posts;
			$view['tags'] = $this->db->tags('name', 'id')->selectAll(
				$view['post']->posts
			);
		}
				
		$this->layout['title'] = 'Curious About Programming';
		$this->view[] = $view;
				
		return $this->render();
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
		
		$this->view['original_route'] = $_GET['route'];
		$this->layout['original_route'] = $_GET['route'];
		$this->layout['title'] = 'View Source';
		
		return $this->render();
	}
	
	/**
	 * About page.
	 */
	public function GET_about() {
		$this->layout['title'] = 'About '. $this->layout['blog_author'];
		return $this->render();
	}
}
