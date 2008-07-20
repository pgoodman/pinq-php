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
	 * $r->render([string $layout_file]) -> string
	 *
	 * Render the layout view. If a $layout_file is provided then it will
	 * override the class property $layout_file.
	 */
	protected function render($layout_file = NULL) {
		
		if($this->isAborted())
			return NULL;
		
		$layout_file = !$layout_file ? $this->layout_file : $layout_file;
		$dir = dirname($this->_file);
		$this->layout->setFile("{$dir}/layouts/{$layout_file}");
		$ret = $this->layout->render(
			$this->import('scope-stack')
		);
		
		unset($this->view, $this->layout);
		
		return $ret;
	}
	
	/**
	 * $r->beforeAction(string $method_name) -> void
	 *
	 * Hooked called before an action is executed. Set the file that the 
	 * view will use.
	 */
	public function beforeAction($method) {
		$this->view->setFile("pages/{$this->_file}/{$method}");
	}
}
