<?php

/**
 * man(string) <==> help(string)
 *
 * @author Peter Goodman
 */
function man($thing) {
	help($thing);
}

/**
 * help(mixed) -> void
 *
 * Given a function name, class name, or object, output a nicely formatted 
 * documentation string.
 *
 * @author Peter Goodman
 */
function help($thing) {
	$str = '';
	
	if(is_string($thing)) {
		
		// existing function
		if(function_exists($thing))
			$str = __doc_format_function(
				__doc__($thing),
				$thing
			);
		
		// existing class/interface
		else if(class_exists($thing, FALSE) || interface_exists($thing, FALSE)) {
			$str = __doc_format_class($thing);
		
		// could be a file rooted somewhere	
		} else {
			if(strpos($thing, '.') !== FALSE) {
				// todo, make it so that a file can also be introspected
			}
		}
	
	// object
	} else if(is_object($thing)) {
		$str = __doc_format_class(get_class($thing));
	
	// unknown
	} else {
		throw new InvalidArgumentException(
			"Function [help] expects either string or object as first ".
			"parameter to be string or object. Neither given."
		);
	}
	
	if(!empty($str))
		echo "<pre>$str</pre>";
}

/**
 * __doc__(mixed $method_or_class[, string $method])__ -> string
 *
 * Gets a nicely formatted doc-block for a function/method/class/object. To
 * get the documentation for a class/method, do: 
 *     __doc__('class name', 'methodname')
 *     __doc__($obj, 'method name')
 *
 * @author Peter Goodman
 */
function __doc__() {
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
	return __doc_format_block($reflector->getDocComment());
}

/**
 * __doc_format_block(string) -> string
 *
 * Format a string as a doc block.
 *
 * @author Peter Goodman
 */
function __doc_format_block($doc_block) {
	$doc_block = preg_replace('~(\r?\n)+~', "\n", $doc_block);
	
	// get rid of leading and trailing multi-line comments delimiters
	$doc_block = preg_replace('~(/[*]{1,2}|[*]/)~', '', $doc_block);
	
	// get rid of leading comment markers (*'s)
	$doc_block = preg_replace('~(\A|\n|\s)+[*][ ]*~', "\n", $doc_block);
	
	// replace php-doc identifiers with something readable
	$doc_block = preg_replace('~@internal~s', '', $doc_block);
	$doc_block = preg_replace('~@(\w+)~e', 'ucfirst("$1").":"', $doc_block);
	
	return trim($doc_block);
}

/**
 * __doc_format_function(string $doc_block, string $function_name) -> string
 *
 * Given a paragraph of documentation and the name of a function, return a
 * nicely formatted string.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_function($doc_block, $function_name) {
	$doc_block = preg_replace('~\n~', "\n    ", "    ".trim($doc_block));
	return "<i>{$function_name}(...)</i>\n{$doc_block}";
}

/**
 * __doc_format_section(string $doc_block, string $prefix) -> string
 *
 * Prefix each line of a documentation block.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_section($doc_block, $prefix) {
	$doc_block = trim($doc_block);
	
	if(!empty($doc_block))
		return preg_replace('~\n~', "\n{$prefix}", "{$prefix}{$doc_block}");
	
	return '';
}

/**
 * __doc_format_constructor(ReflectionClass) -> string
 *
 * Format the constructor of a class.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_constructor(ReflectionClass $reflector) {
	try {
		$constructor = $reflector->getConstructor();
		if($constructor instanceof ReflectionMethod)
			return __doc_format_block($constructor->getDocComment());
	
	// ignore it
	} catch(Exception $e) {}
	
	return '';
}

/**
 * __doc_get_child_classes(string $class_name, [array &$parent[, array &$seen]]) 
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
function __doc_get_class_descendants($class_name, 
	                                 array &$parent = array(), 
	                                 array &$seen = array()) {
	
	if(!class_exists($class_name, FALSE) && !interface_exists($class_name, FALSE)) {
		throw new InvalidArgumentException(
			"The class/interface [{$class_name}] does not exist."
		);
	}
	
	// merge the list of declared classes and iterators
	$classes = array_merge(
		get_declared_interfaces(),
		get_declared_classes()
	);
	
	// go through the defined interfaces and classes and recursively build a
	// tree of the extending classes/interfaces
	foreach($classes as $class) {
		
		if(isset($seen[$class]))
			continue;
		
		// look at its parent class
		if(get_parent_class($class) == $class_name)
			$parent[$class] = array();
		
		// look at its parent interfaces
		else {
			$interfaces = class_implements($class, FALSE);
			
			if(in_array($class_name, $interfaces))
				$parent[$class] = array();
		}
		
		// get the descendants recursively
		if(isset($parent[$class])) {
			$seen[$class] = TRUE;
			__doc_get_class_descendants($class, $parent[$class], $seen);
		}
		
		// sort this level's classes
		ksort($parent);
	}
	
	return $parent;
}

/**
 * __doc_format_class_descendants(array $descendants[, int $level]) -> string
 *
 * Return a formatted tree of class descendants from an array (tree) of class
 * descendants. This function builds the formatted string recursively.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_class_descendants(array $descendants, $level = 0) {
	
	$str = '';
	$level = abs($level);
	
	// go over the descendants and build up the string
	foreach($descendants as $class => $sub_classes) {
		
		$str .= "\n";
		
		if($level > 0)
			$str .= str_repeat('    ', $level);
		
		$str .= $class;
		
		// recursively build up the sub-classes
		$str .= __doc_format_class_descendants($sub_classes, $level+1);
	}
	
	return $str;
}

/**
 * __doc_format_class(string $class_name) -> string
 *
 * Given a class or interface name, return a nicely formatted documentation 
 * string describing the public, private, and protected methods of that class.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_class($class_name) {
	
	// class doesn't exist
	if(!class_exists($class_name, FALSE) && !interface_exists($class_name, FALSE)) {
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
	$doc_block = __doc_format_block($reflector->getDocComment());
	
	// can this class be extended?
	$final = $reflector->isFinal() && !$is_interface ? 'final ' : '';
	
	// the type of the class
	$type = $is_interface ? 'interface' : (
		$reflector->isAbstract() ? 'abstract class' : 'class'
	);
	
	$str = "<b>{$final}{$type} {$class_name}</b>\n";
	$str .= __doc_format_section($doc_block, $line_prefix);
	
	// show the class constants
	$constants = $reflector->getConstants();
	if(!empty($constants)) {
		$str .= "{$section_prefix}{$line_prefix}<u>Constants:</u>";
		
		foreach($constants as $name => $value)
			$str .= "\n{$method_prefix} {$name} -> {$value}";
	}
	
	// format the class constructor (if it has one)
	$doc_block = __doc_format_constructor($reflector);
	if(!empty($doc_block)) {
		$str .= "{$section_prefix}{$line_prefix}<u>Constructor:</u>";
		$str .= $section_prefix. __doc_format_section(
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
			
			$static = $method->isStatic() ? 'static ' : '';
			
			// add in the formatted section
			$str .= $section_prefix . __doc_format_section(
				__doc_format_function(
					__doc_format_block($method->getDocComment()),
					$static . $method->getName()
				),
				$method_prefix
			);
		}
	}
	
	// get the extending classes
	$classes = array();
	__doc_get_class_descendants($class_name, $classes);
	
	// there are parent classes so format them
	if(!empty($classes)) {
		$str .= "{$section_prefix}{$line_prefix}<u>Class Descendants:</u>\n";
		$str .= "{$line_prefix}\n";
		$str .= __doc_format_section(
			__doc_format_class_descendants($classes),
			$method_prefix
		);
	}
	
	return $str;
}
