<?php

class UsersController extends AppResource {
	
	/**
	 * Show the user registration form.
	 */
	public function GET_register() {
				
		if($this->auth->isLogged())
			yield(ERROR_500);
	}
	
	/**
	 * Register a new user.
	 */
	public function POST_register() {
		
		if($this->auth->isLogged())
			yield(ERROR_500);
		
		$this->history->exclude();
		
		try {
			$this->db->users->register($_POST);
		} catch(FailedValidationException $e) {
			
			$this->view['form_errors'] = $e->getErrors();
			yield('/users/register', 'GET');
		}
		
		// log the user in
		$this->auth->login($_POST['email'], $_POST['password'], TRUE);
		
		// redirect to previous page
		redirect($this->history->pop());
	}
}