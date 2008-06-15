<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * A buffer to hold temporary output information. Why use this? It's often the
 * case that when people are making calls to header() and outputting data at
 * the same time a "headers have already been sent" error is triggered. Using
 * the out() and stop() functions allow one to reliably forgo these errors.
 * @author Peter Goodman
 */
class OutputBuffer {
	
	static public $buffer = "";
	
	/**
	 * Set output compression.
	 */
	static public function compress() {
		/*ini_set('output_handler', '');
		ini_set('zlib.output_compression', 'On');
		ini_set('zlib.output_compression_level', 6);*/
	}
	
	/**
	 * Flush the output buffer.
	 */
	static public function flush() {
		echo self::$buffer;
		self::$buffer = '';
	}
}

/**
 * Send some data to the output buffer.
 * @author Peter Goodman
 */
function out() {
	$args = func_get_args();
	OutputBuffer::$buffer .= implode('', $str);
}

/**
 * Send data to the output buffer and follow it with a new-line character.
 * @author Peter Goodman
 */
function outln() {
	$args = func_get_args();
	OutputBuffer::$buffer .= implode("\n", $str) ."\n";
}

/**
 * Stop output. Analogous to PHP's exit() function, except that instead of
 * exiting it throws an exception to flush the output buffer.
 * @author Peter Goodman
 */
function stop() {
	throw new FlushBufferException;
}
