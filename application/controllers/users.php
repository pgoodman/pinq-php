<?php

class UsersController extends AppController {
	
	public function GET_index($id = '') {
		
		if(!($user = $this->db->users->getBy('id', base36_decode($id))))
			yield(ERROR_404);
			
		$this->view['user'] = $user;
	}
}