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
	return  View::factory()->setFile((string)$id, View::ELEMENT);
}

/**
 * page_view(string $id) -> View
 *
 * Create a new page view element.
 *
 * @author Peter Goodman
 */
function page_view($id) {
	return View::factory()->setFile((string)$id, View::PAGE);
}

/**
 * layout_view(string $id) -> View
 *
 * Create a new layout view element.
 *
 * @author Peter Goodman
 */
function layout_view($id) {
	return View::factory()->setFile((string)$id, View::LAYOUT);
}

/**
 * An abstract view.
 *
 * @author Peter Goodman
 */
abstract class View extends Dictionary implements ConfigurablePackage {
	
	// view types, nicer to get at than using the plural strings
	const LAYOUT = 'layouts',
	      ELEMENT = 'elements',
	      PAGE = 'pages';
	
	// the class name for this package, normally would be PinqView if the
	// package is not being extended
	static public $class;
	
	/**
	 * View::configure(PackageLoader, ConfigLoader, array) -> void
	 *
	 * Configure the view.. this is sort of a hack to make extending views
	 * right.
	 *
	 * TODO: find a way around this hack.
	 */
	static public function configure(Loader $p, Loader $c, array $args) {
		self::$class = $args['class'];
	}
	
	/**
	 * View::factory(void) -> View
	 *
	 * Factory method to return a new view instance.
	 */
	static public function factory() {
		$args = func_get_args();
		return call_user_class_array(self::$class, $args);
	}
	
	/**
	 * $v->render(StackOfDictionaries) -> void
	 */
	abstract public function render(StackOfDictionaries $scope);
	
	/**
	 * $v->setFile(string $file, int $type) -> View
	 */
	abstract public function setFile($file, $type);
}

/**
 * Represents a single view.
 * @author Peter Goodman
 */
class PinqView extends View {
	
	protected $file;
	
	/**
	 * $v->setFile(string $file, int $type) -> View
	 *
	 * Set a file to the view. This is not part of the constructor so that
	 * the views can be bound to a file after they've been created.
	 */
	public function setFile($file, $type) {
		
		$file = DIR_APPLICATION ."/views/{$type}/{$file}.html";
				
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
			($vars instanceof Record) ? $vars->toArray() : (array)$vars
		);
		
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
