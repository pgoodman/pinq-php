<?php

/* $Id$ */

/**
 * Default error controller, doesn't actually need to be changed.
 *
 * TODO: the error message from an exception should be passed in here somehow.
 *
 * @author Peter Goodman
 */
class ErrorLocalResource extends AppLocalResource {	
	
	//public $layout_file = 'error';
	
	public function ANY_401() { set_http_status(401); return $this->render(); }
	public function ANY_403() { set_http_status(403); return $this->render(); }
	public function ANY_404() { set_http_status(404); return $this->render(); }
	public function ANY_405() { set_http_status(405); return $this->render(); }
	public function ANY_500() { set_http_status(500); return $this->render(); }
	public function ANY_validation() { 
		set_http_status(500); return $this->render();
	}
}
