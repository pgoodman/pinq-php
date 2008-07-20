<?php

/* $Id$ */

/**
 * Default error controller, doesn't actually need to be changed.
 *
 * TODO: the error message from an exception should be passed in here somehow.
 *
 * @author Peter Goodman
 */
class ErrorResourceText extends AppResourceText {	
	
	//public $layout_file = 'error';
	
	public function ANY_401() { Http::setStatus(401); return $this->render(); }
	public function ANY_403() { Http::setStatus(403); return $this->render(); }
	public function ANY_404() { Http::setStatus(404); return $this->render(); }
	public function ANY_405() { Http::setStatus(405); return $this->render(); }
	public function ANY_500() { Http::setStatus(500); return $this->render(); }
	public function ANY_validation() { 
		Http::setStatus(500); return $this->render();
	}
}
