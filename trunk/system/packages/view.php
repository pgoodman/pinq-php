<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * element_view(string $id) -> View
 *
 * Create a new element view element.
 *
 * @author Peter Goodman
 */
function element_view($id) {
	return  call_user_class_array('PinqView')->setFile(
		(string)$id, 
		PinqView::ELEMENT
	);
}

/**
 * page_view(string $id) -> View
 *
 * Create a new page view element.
 *
 * @author Peter Goodman
 */
function page_view($id) {
	return call_user_class_array('PinqView')->setFile(
		(string)$id, 
		PinqView::PAGE
	);
}

/**
 * layout_view(string $id) -> View
 *
 * Create a new layout view element.
 *
 * @author Peter Goodman
 */
function layout_view($id) {
	return call_user_class_array('PinqView')->setFile(
		(string)$id, 
		PinqView::LAYOUT
	);
}

/**
 * Represents a single view.
 * @author Peter Goodman
 */
class PinqView extends Dictionary implements InstantiablePackage, Factory {
	
	protected $file;
	
	// the class name for this package, normally would be PinqView if the
	// package is not being extended
	static public $_class;
	
	// view types, nicer to get at than using the plural strings
	const LAYOUT = 'layouts',
	      ELEMENT = 'elements',
	      PAGE = 'pages';
	
	/**
	 * PinqView::factory(void) -> PinqView
	 *
	 * Factory method to return a new view instance. If this class is being
	 * extended in the /application/packages directory then $_class will be
	 * that class name.
	 */
	static public function factory() {
		$class = self::$_class;
		return new $class;
	}
	
	/**
	 * $v->setFile(string $file, int $type) -> PinqView
	 *
	 * Set a file to the view. This is not part of the constructor so that
	 * the views can be bound to a file after they've been created.
	 */
	public function setFile($file, $type) {
		
		$file = realpath(DIR_APPLICATION ."/views/{$type}/{$file}.html");
		
		// only set the file if it exists
		if(is_readable($file))
			$this->file = $file;
		
		return $this;
	}
	
	/**
	 * $v->render(StackOfDictionaries[, {array, Record} $vars]) -> void
	 *
	 * Render a view. This also allows for easier applying of vars through
	 * the secodn parameter.
	 */
	public function render(StackOfDictionaries $scope, $vars = array()) {
		
		if(NULL === $this->file)
			return;
		
		// set the immediate vars
		$immediate_vars = array_merge(
			$this->toArray(),
			($vars instanceof Dictionary) ? $vars->toArray() : (array)$vars
		);
		
		// don't allow hijacking of either $scope or $this
		unset($immediate_vars['scope'], $immediate_vars['this']);
		
		$scope->push($immediate_vars);
		extract($scope->top(), EXTR_REFS | EXTR_OVERWRITE);
		
		// bring in the view
		include $this->file;
		
		$scope->pop();
	}
}
