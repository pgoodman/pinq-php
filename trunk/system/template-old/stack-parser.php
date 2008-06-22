<?php

define('DIR_SYSTEM', true);

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

require_once dirname(__FILE__) .'/stack.php';

/**
 * An abstract stack parser.
 * @author Peter Goodman
 */
class StackParser extends Stack {
	
	protected $_pattern,
	          $_num_sub_patterns;
	
	const NEXT = 1,
	      PUSH = 2,
	      POP = 4,
	      IGNORE = 8,
	      ERROR = 16;
	
	/**
	 * Set a regular expression pattern that the parser will split input by.
	 * @internal
	 */
	protected function setSplitPattern($pattern) {
		$this->_pattern = $pattern;
		
		// match all the top-level sub-patterns in the regular expression
		$matches = array();
		$matches = preg_match_all(
			'~\( ( (?>[^()]+) | (?R) )* \)~x', 
			$pattern, 
			$matches
		);
		
		// no sub-patterns were found within the pattern
		if(!$matched) {
			throw new InvalidArgumentException(
				"StackParser::setPattern expected regular expression ".
				"pattern to have sub-patterns."
			);
		}
		
		// we are going to have a separate handle for each sub-pattern within
		// the pattern, hence why we need to count them
		$this->_num_sub_patterns = count($matches[0]);
	}
	
	/**
	 * Parse the input by the split pattern.
	 */
	public function parse($input) {
		
		if(NULL === $this->_pattern) {
			throw new BadMethodCallException(
				"StackParser::parse() must be called after setting a regular ".
				"expression pattern to split the input by."
			);
		}
		
		// split up the input by the pattern
		$parts = preg_split(
			$this->_pattern, 
			$input, 
			-1, 
			PREG_SPLIT_DELIM_CAPTURE
		);
		
		$num_parts = count($parts);
		
		for($i = 0; $i < $num_parts; $i += $this->_num_sub_patterns) {
			for($h = 0; $h < $this->_num_sub_patterns; $h++) {
				$func = 'parsePatternPart'. $h;
				
				switch($this->$func($parts[$i+$h])) {
					case self::PUSH:
						
					
					case self::POP:
						
					
					case self::NEXT:
						
					
					case self::IGNORE:
						
				}
			}
		}
	}
}

class TemplateStackParser extends StackParser {
	public function __construct() {
		$this->setPattern('~<(/?)([a-z0-9_]+):([a-z0-9_]+)((?: [^>]*)?)(/?)>~i');
	}
	
	public function 
}