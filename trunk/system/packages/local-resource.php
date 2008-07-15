<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Represents a request to a certain subset of available events. Events are
 * represented by public methods prefixed with either a specific request type
 * (in uppercase) or ANY, followed by an underscore. For example: ANY_index().
 *
 * @author Peter Goodman
 */
abstract class PinqLocalResource extends Resource implements Package {
	
	// package loader
	protected $view, // page view
	          $layout, // layout view
	          $render_layout = TRUE,
	          $layout_file = 'default';
	
	/**
	 * $r->__init__(void) -> void
	 *
	 * Crete the page an layout views for this local resource.
	 */
	protected function __init__() {		
		$this->layout = $this->importNew('view');
		$this->view = $this->importNew('view');
		$this->layout['page_view'] = $this->view;
	}
	
	/**
	 */
	protected function __del__() {		
		
		// set the layout file and render it
		if(!$this->isAborted() && $this->render_layout) {
			$this->layout->setFile(
				dirname($this->_file) .'/'. $this->layout_file, 
				PinqView::LAYOUT
			);
			$this->layout->render($this->import('scope-stack'));
		}
		
		unset(
			$this->layout,
			$this->view
		);
	}
	
	/**
	 * $r->beforeAction(string $method_name) -> void
	 *
	 * Hooked called before an action is executed. Set the file that the 
	 * view will use.
	 */
	public function beforeAction($method) {
		$this->view->setFile("{$this->_file}/{$method}", PinqView::PAGE);
	}
}
