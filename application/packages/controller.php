<?php

class AppController extends PinqController {
	
	protected $db;
	
	/**
	 * Constructor hook.
	 */
	protected function __init__() {
		$this->db = $this->import('db.blog');
	}
	
	/**
	 * Destructor hook.
	 */
	protected function __del__() {
		unset($this->db);
	}
}