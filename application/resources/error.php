<?php

/* $Id$ */

/**
 * Default error controller, doesn't actually need to be changed.
 *
 * TODO: the error message from an exception should be passed in here somehow.
 *
 * @author Peter Goodman
 */
class ErrorResource extends AppResource {	
		
	protected function render($layout_file = 'error') {
		
		$type = Http::getPreferredContentType(
			'text/html',
			'application/xhtml+xml',
			'application/xml',
			'application/json',
		);
		
		switch($type) {
			case 'text/html':
			case 'application/xhtml+xml':
			case 'application/xml':
				return parent::render('error');
			
			case 'application/json':
			case 'text/x-json':
				return '{}';
			
			default:
				return '';
		}
	}
	
	public function ANY_401() {
		Http::setStatus(401); 
		return $this->render(); 
	}
	
	public function ANY_403() { 
		Http::setStatus(403); 
		return $this->render(); 
	}
	
	public function ANY_404() { 
		Http::setStatus(404); 
		return $this->render(); 
	}
	
	public function ANY_405() { 
		Http::setStatus(405); 
		return $this->render(); 
	}
	
	public function ANY_500() { 
		Http::setStatus(500); 
		return $this->render(); 
	}
	
	public function ANY_validation() { 
		Http::setStatus(500); 
		return $this->render();
	}
}