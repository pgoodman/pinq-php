<?php

/**
 * Posts controller.
 */
class PostsLocalResource extends AppLocalResource {
	
	/**
	 * View a single post and its comments.
	 */
	public function GET_index($nice_title = '') {
		
		// get the post and if it doesn't exist or is a comment then error
		$post = $this->db->posts->selectBy('nice_title', $nice_title);
		
		if(!$post || $post['parent_id'] > 0)
			yield(ERROR_404);
		
		// get the comments
		$comments = NULL;
		if($post['num_children'] > 0)
			$comments = $this->db->posts->selectAll($post->posts);
		
		// set data to the view
		$this->layout['title'] = $post['title'];
		$this->view[] = array(
			'post' => $post,
			'tags' => $this->db->tags->selectAll($post->posts),
			'comments' => $comments,
		);
		
		return $this->render();
	}
	
	/**
	 * Add a new comment.
	 */
	public function POST_index($nice_title = '') {
		
		$post = $this->db->posts->selectBy('nice_title', $nice_title);
		
		// people aren't (currently) allowed to post to sub-level posts
		if(!$post || $post['parent_id'] != 0)
			yield(ERROR_404);
		
		if(!$this->auth->isLogged()) {
			
			$form_errors = array();
			
			if(!$_POST['regme']) {
				
				// try to log the poster in
				$this->auth->login(
					$_POST['email'], 
					$_POST['password'], 
					TRUE
				
				// login failed
				) or $form_errors = array(
					'logme' => array('Invalid email/password combination.'),
				);
			
			} else {
				try {
					$this->db->users->register($_POST);
				} catch(FailedValidationException $e) {
					$form_errors = $e->getErrors();
				}
			}
			
			if(!empty($form_errors)) {
				$this->view['form_errors'] = $form_errors;
				yield(get_route(), 'GET');
			}
		}
		
		try {
			$this->db->insert(into('posts')->set(array(
				'parent_id' => $post['id'],
				'id' => NULL,
				'body' => $_POST['body'],
				'title' => NULL,
				'user_id' => NULL,
			)));
			
		} catch(FailedValidationException $e) {
			$this->view['form_errors'] = $e->getErrors();
			yield(get_route(), 'GET');
		}
		
		return $this->render();
	}
}
