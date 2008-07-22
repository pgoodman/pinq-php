<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * help(mixed) -> void
 *
 * Given a function name, class name, or object, output a nicely formatted 
 * documentation string.
 *
 * @author Peter Goodman
 */
function help($thing, $method = NULL) {
	$str = '';
	
	if($method !== NULL) {
		$str = Doc::formatFunction(
			Doc::get($thing, $method),
			$method
		);
	
	} else if(is_string($thing)) {
		
		// existing function
		if(function_exists($thing)) {
			$str = Doc::formatFunction(
				Doc::get($thing),
				$thing
			);
		
		// existing class/interface
		} else if(class_exists($thing, FALSE) || interface_exists($thing, FALSE)) {
			$str = Doc::formatClass($thing);
		
		// could be a file rooted somewhere	
		} else {
			if(strpos($thing, '.') !== FALSE) {
				// todo, make it so that a file can also be introspected
			}
		}
	
	// object
	} else if(is_object($thing)) {
		$str = Doc::formatClass(get_class($thing));
	
	// unknown
	} else {
		throw new InvalidArgumentException(
			"Function [help] expects either string or object as first ".
			"parameter to be string or object. Neither given."
		);
	}
	
	if(!empty($str)) {
		echo "<pre>$str</pre>";
		flush();
	}
}


/**
 * Class that handles building up class / function document strings. It is
 * abstract because all methods are static and this is a non-instantiable
 * class.
 *
 * @author Peter Goodman
 */
abstract class Doc {
	
	/**
	 * Doc::get(mixed $method_or_class[, string $method])__ -> string
	 *
	 * Gets a nicely formatted doc-block for a function/method/class/object. To
	 * get the documentation for a class/method, do: 
	 *     __doc__('class name', 'methodname')
	 *     __doc__($obj, 'method name')
	 *
	 * @author Peter Goodman
	 */
	static public function get() {

		$reflector = NULL;
		$callback = func_get_args();

		if(!isset($callback[0]))
			return '';

		try {
			// class/object method
			if(count($callback) === 2)
				$reflector = new ReflectionMethod($callback[0], $callback[1]);

			// obect
			else if(is_object($callback[0]))
				$reflector = new ReflectionObject($callback[0]);

			// function/class
			else {
				if(function_exists($callback[0]))
					$reflector = new ReflectionFunction($callback[0]);

				else if(class_exists($callback[0], FALSE) || 
				        interface_exists($callback[0], FALSE))
					$reflector = new ReflectionClass($callback[0]);

				else
					return '';
			}

		} catch(Exception $e) {
			return '';
		}

		// get and return the formatted doc block
		return self::formatBlock($reflector->getDocComment());
	}
	
	/**
	 * Doc::formatBlock(string) -> string
	 *
	 * Format a string as a doc block.
	 */
	static public function formatBlock($doc_block) {
		$doc_block = preg_replace('~(\r?\n)+~', "\n", $doc_block);

		// get rid of leading and trailing multi-line comments delimiters
		$doc_block = preg_replace('~(/[*]{1,2}|[*]/)~', '', $doc_block);

		// get rid of leading comment markers (*'s)
		$doc_block = preg_replace('~(\n|\A)?(?<!\w)\s+[*][ ]?~', "\n", $doc_block);

		// replace php-doc identifiers with something readable
		$doc_block = preg_replace('~@internal~s', '', $doc_block);
		$doc_block = preg_replace('~@(\w+)~e', 'ucfirst("$1").":"', $doc_block);

		return htmlentities(trim($doc_block), ENT_QUOTES);
	}
	
	/**
	 * Doc::formatFunction(string $doc_block, string $function_name) -> string
	 *
	 * Given a paragraph of documentation and the name of a function, return a
	 * nicely formatted string.
	 *
	 * @internal
	 */
	static public function formatFunction($doc_block, $function_name) {
		$doc_block = preg_replace('~\n~', "\n    ", "    ".trim($doc_block));
		return "<i>{$function_name}(...)</i>\n{$doc_block}";
	}
	
	/**
	 * Doc::formatSection(string $doc_block, string $prefix) -> string
	 *
	 * Prefix each line of a documentation block.
	 *
	 * @internal
	 */
	static protected function formatSection($doc_block, $prefix) {
		$doc_block = trim($doc_block);

		if(!empty($doc_block))
			return preg_replace('~\n~', "\n{$prefix}", "{$prefix}{$doc_block}");
		
		return '';
	}
	
	/**
	 * Doc::formatConstructor(ReflectionClass) -> string
	 *
	 * Format the constructor of a class.
	 *
	 * @internal
	 */
	static protected function formatConstructor(ReflectionClass $reflector) {
		try {
			$constructor = $reflector->getConstructor();
			if($constructor instanceof ReflectionMethod)
				return self::formatBlock($constructor->getDocComment());

		// ignore it
		} catch(Exception $e) {}

		return '';
	}
	
	/**
	 * Doc::getClassDescendants(string $class_name, [array &$parent[, array &$classes]]) 
	 * -> array
	 * 
	 * Return a tree of the classes/interfaces the extend/implement this class or
	 * interface. If the class or interface name passed in does not exist then
	 * an InvalidArgumentException will be thrown.
	 *
	 * @note This function is anything but efficient so use it sparingly.
	 * @author Peter Goodman
	 * @internal
	 */
	static protected function getClassDescendants($class_name, 
	                                       array &$parent = array(), 
	                                       array &$classes = array()) {

		if(!class_exists($class_name, FALSE) && 
		   !interface_exists($class_name, FALSE)) {
			throw new InvalidArgumentException(
				"The class/interface [{$class_name}] does not exist."
			);
		}

		// merge the list of declared classes and iterators. we only find these
		// once and them subtract from them
		if(empty($classes)) {
			$classes = array_merge(
				get_declared_interfaces(),
				get_declared_classes()
			);
		}

		// go through the defined interfaces and classes and recursively build a
		// tree of the extending classes/interfaces
		foreach($classes as $i => $class) {

			if($class === NULL)
				continue;

			// look at its parent class
			if(get_parent_class($class) == $class_name)
				$parent[$class] = array();

			// look at its parent interfaces, this won't actually get a proper
			// tree of things given that multiple interfaces can be extended/
			// implemented at once, but it will give a good idea of things
			else {
				$interfaces = class_implements($class, FALSE);
				$interfaces = array_intersect(
					$interfaces === FALSE ? array() : $interfaces,
					$classes
				);

				if(in_array($class_name, $interfaces))
					$parent[$class] = array();
			}

			// get the descendants recursively
			if(isset($parent[$class])) {
				$classes[$i] = NULL;
				self::getClassDescendants($class, $parent[$class], $classes);
			}

			// sort this level's classes
			ksort($parent);
		}

		return $parent;
	}
	
	/**
	 * Doc::formatClassDescendants(array $descendants[, int $level]) -> string
	 *
	 * Return a formatted tree of class descendants from an array (tree) of 
	 * class descendants. This function builds the formatted string recursively.
	 *
	 * @internal
	 */
	static protected function formatClassDescendants(array $descendants, 
	                                                       $level = 0) {

		$str = '';
		$level = abs($level);

		// go over the descendants and build up the string
		foreach($descendants as $class => $sub_classes) {
			$str .= "\n";

			if($level > 0)
				$str .= str_repeat('    ', $level);

			$str .= $class;

			// recursively build up the sub-classes
			$str .= self::formatClassDescendants($sub_classes, $level+1);
		}

		return $str;
	}
	
	/**
	 * Doc::formatClass(string $class_name) -> string
	 *
	 * Given a class or interface name, return a nicely formatted documentation 
	 * string describing the public, private, and protected methods of that class.
	 *
	 * @internal
	 */
	static public function formatClass($class_name) {

		// class doesn't exist
		if(!class_exists($class_name, FALSE) && 
		   !interface_exists($class_name, FALSE)) {
			throw new InvalidArgumentException(
				"Class/Interface [{$class_name}] does not exist and therefore ".
				"cannot be introspected."
			);
		}

		// get the reflector
		try {
			$reflector = new ReflectionClass($class_name);
		} catch(Exception $e) {
			return '';
		}

		$is_interface = $reflector->isInterface();

		// prefixes
		$line_prefix = ' |  ';
		$section_prefix = "\n{$line_prefix}\n";
		$method_prefix = "{$line_prefix}    ";

		// the doc block for the class
		$doc_block = self::formatBlock($reflector->getDocComment());

		// can this class be extended?
		$final = $reflector->isFinal() && !$is_interface ? 'final ' : '';

		// the type of the class
		$type = $is_interface ? 'interface' : (
			$reflector->isAbstract() ? 'abstract class' : 'class'
		);

		$str = "<b>{$final}{$type} {$class_name}</b>";
		if(!empty($doc_block))
			$str .= "\n". self::formatSection($doc_block, $line_prefix);

		// show the class constants
		$constants = $reflector->getConstants();
		if(!empty($constants)) {
			$str .= "{$section_prefix}{$line_prefix}<u>Constants:</u>";

			foreach($constants as $name => $value)
				$str .= "\n{$method_prefix} {$name} -> {$value}";
		}

		// format the class constructor (if it has one)
		$doc_block = self::formatConstructor($reflector);
		if(!empty($doc_block)) {
			$str .= "{$section_prefix}{$line_prefix}<u>Constructor:</u>";
			$str .= $section_prefix. self::formatSection(
				$doc_block,
				$method_prefix
			);
		}

		// get and sort out the methods of this class
		$methods = $reflector->getMethods();
		$types = array(array(), array(), array());
		$headers = array(
			'Public Methods:', 
			'Protected Methods:', 
			'Private Methods:',
		);

		// sort the methods into {0:public, 1:protected, 2:private}
		foreach($methods as $method) {
			$type = $method->isPublic() ? 0 : ($method->isProtected() ? 1 : 2);
			$types[$type][$method->getName()] = $method;
		}

		// output the method infos
		foreach($headers as $i => $header) {
			if(empty($types[$i]))
				continue;

			$str .= "{$section_prefix}{$line_prefix}<u>{$header}</u>";

			ksort($types[$i]);
			foreach($types[$i] as $method) {

				// ignore the constructor and destructor
				if($method->isConstructor() || $method->isDestructor())
					continue;

				// method modifiers (static, abstract)
				$modifiers = array();
				$method->isStatic() && ($modifiers[] = 'static');
				$method->isAbstract() && ($modifiers[] = 'abstract');
				if(!empty($modifiers))
					$modifiers = '&lt;'. implode(', ', $modifiers) .'&gt;';
				else
					$modifiers = '';

				// add in the formatted section
				$str .= $section_prefix . self::formatSection(
					self::formatFunction(
						self::formatBlock($method->getDocComment()),
						$method->getName() . $modifiers
					),
					$method_prefix
				);
			}
		}

		// get the extending classes
		$classes = array();
		self::getClassDescendants($class_name, $classes);

		// there are parent classes so format them
		if(!empty($classes)) {
			$str .= "{$section_prefix}{$line_prefix}<u>Class Descendants:</u>\n";
			$str .= "{$line_prefix}\n";
			$str .= self::formatSection(
				self::formatClassDescendants($classes),
				$method_prefix
			);
		}

		return $str;
	}
}
