<?php

class UsersController extends AppController {
	
	/**
	 * Show the user registration form.
	 */
	public function GET_register() {
				
		if($this->auth->isLogged())
			yield(ERROR_500);
		
		// shows the view...
	}
	
	/**
	 * Register a new user.
	 */
	public function POST_register() {
		
		if($this->auth->isLogged())
			yield(ERROR_500);
		
		$errors = array();
		
		try {
			if($this->db->users->getBy('email', $_POST['email']))
				$errors[] = 'email';
			
			if(!empty($errors))
				throw new FailedValidationException($errors);
			
			$this->db->insert(to('users')->set($_POST));
		
		} catch(FailedValidationException $e) {
			$this->view['form_errors'] = $e->getErrors();
			yield('/users/register', 'GET');
		}
		
		// log the user in
		$this->auth->login($_POST['email'], $_POST['password']);
		
		redirect($this->history->pop());
	}
}