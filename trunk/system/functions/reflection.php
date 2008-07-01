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
	$thing = trim($thing);
	
	if(is_string($thing)) {
		
		// existing function
		if(function_exists($thing))
			$str = __doc_format_function(
				__doc__($thing),
				$thing
			);
		
		// existing class/interface
		else if(class_exists($thing) || interface_exists($thing)) {
			$str = __doc_format_class($thing);
		
		// could be a file rooted somewhere	
		} else {
			if(strpos($thing, '.') !== FALSE) {
				// todo
			}
		}
	
	// object
	} else if(is_object($thing)) {
		$str = __doc_format_class(class_name($thing));
	
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
 * __doc__(mixed[, string])__ -> string
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
			
			else if(class_exists($callback[0]) || interface_exists($callback[0]))
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
 * __doc_format_function(string, string) -> string
 *
 * Given a paragraph of documentation and the name of a function, return a
 * nicely formatted string.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_function($doc_block, $name) {
	$doc_block = preg_replace('~\n~', "\n    ", "    ".trim($doc_block));
	return "{$name}(...)\n{$doc_block}";
}

/**
 * __doc_format_section(doc_block, prefix) -> string
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
 * __doc_format_class(string) -> string
 *
 * Given a class or interface name, return a nicely formatted documentation 
 * string describing the public, private, and protected methods of that class.
 *
 * @author Peter Goodman
 * @internal
 */
function __doc_format_class($class_name) {
	
	// class doesn't exist
	if(!class_exists($class_name) && !interface_exists($class_name)) {
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
	
	$str = "{$final}{$type} {$class_name}\n";	
	$str .= __doc_format_section($doc_block, $line_prefix);
	
	// show the class constants
	$constants = $reflector->getConstants();
	if(!empty($constants)) {
		$str .= "{$section_prefix}{$line_prefix}Constants:";
		
		foreach($constants as $name => $value)
			$str .= "\n{$method_prefix} {$name} -> {$value}";
	}
	
	// format the class constructor (if it has one)
	$doc_block = __doc_format_constructor($reflector);
	if(!empty($doc_block)) {
		$str .= "{$section_prefix}{$line_prefix}Constructor:";
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
		
		$str .= "{$section_prefix}{$line_prefix}{$header}";
		
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
	
	return $str;
}
