<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Create a new element view element.
 * @author Peter Goodman
 */
function element_view($id) {
	$view = View::factory();
	$view->setFile($id, View::ELEMENT);
	return $view;
}

/**
 * Create a new page view element.
 * @author Peter Goodman
 */
function page_view($id) {
	$view = View::factory();
	$view->setFile($id, View::PAGE);
	return $view;
}

/**
 * Create a new layout view element.
 * @author Peter Goodman
 */
function layout_view($id) {
	$view = View::factory();
	$view->setFile($id, View::LAYOUT);
	return $view;
}

/**
 * A view.
 * @author Peter Goodman
 */
abstract class View extends Dictionary implements Package {
	
	// view types, nicer to get at than using the plural strings
	const LAYOUT = 'layouts',
	      ELEMENT = 'elements',
	      PAGE = 'pages';
	
	// the class name for this package, normally would be PinqView if the
	// package is not being extended
	static public $class;
	
	/**
	 * Configure the view.. this is sort of a hack to make extending views
	 * right.
	 *
	 * TODO: find a way around this hack.
	 */
	static public function configure(Loader $a, Loader $b, array $args) {
		self::$class = $args['class'];
	}
	
	/**
	 * Factory method to return a new view instance.
	 */
	static public function factory() {
		$args = func_get_args();
		return call_user_class_array(self::$class, $args);
	}
	
	abstract public function render(StackOfDictionaries $view);
	abstract public function setFile($file, $category);
}

/**
 * Represents a single view.
 * @author Peter Goodman
 */
class PinqView extends View implements ConfigurablePackage {
	
	protected $file;
	
	/**
	 * Set a file to the view. This is not part of the constructor so that
	 * the views can be bound to a file after they've been created.
	 */
	public function setFile($file, $type) {
				
		$file = DIR_APPLICATION ."/views/{$type}/{$file}.html";
		
		var_dump($file);
		
		// uh oh, we will fail silently
		if(!is_readable($file))
			return;
		
		$this->file = $file;
	}
	
	/**
	 * Render a view.
	 */
	public function render(StackOfDictionaries $scope) {
		
		if(NULL === $this->file)
			return;
		
		$immediate_vars = $this->toArray();
		
		// push the immediate variables onto the scope stack
		$scope->push($immediate_vars);
		
		// don't allow hijacking of either the scope or $this
		unset($immediate_vars['scope'], $immediate_vars['this']);
		
		// extract the immediate variables into the current scope
		extract($scope->top(), EXTR_REFS | EXTR_OVERWRITE);
		
		// bring in the view
		include $this->file;
		
		// pop this scopes variables off the scope stack
		$scope->pop();
	}
}
