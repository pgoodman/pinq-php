<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class FlushBufferException extends PinqException {
	
}

/**
 * A buffer to hold temporary output information. Why use this? It's often the
 * case that when people are making calls to header() and outputting data at
 * the same time a "headers have already been sent" error is triggered. Using
 * the out() and stop() functions allow one to reliably forgo these errors.
 * @author Peter Goodman
 */
class OutputBuffer {
	
	static public $buffers = array(
		'out' => '',
		'err' => '',
	);
	
	/**
	 * Clear the current buffer.
	 */
	static public function clear($which) {
		
		// whoops :P
		if(!isset(self::$buffers[$which])) {
			throw new InvalidArgumentException(
				"Output buffer [{$which}] does not exist."
			);
		}
		
		self::$buffers[$which] = '';
	}
	
	/**
	 * Clear all output buffers.
	 */
	static public function clearAll() {
		self::$buffers = array(
			'out' => '',
			'err' => '',
		);
	}
	
	/**
	 * Flush the output buffer.
	 */
	static public function flush($which) {
		
		// whoops :P
		if(!isset(self::$buffers[$which])) {
			throw new InvalidArgumentException(
				"Output buffer [{$which}] does not exist."
			);
		}
		
		echo self::$buffers[$which];
		self::clear($which);
	}
}

/**
 * Send some data to the output buffer.
 * @author Peter Goodman
 */
function out() {
	$args = func_get_args();	
	OutputBuffer::$buffers['out'] .= implode('', $args);
}

/**
 * Send data to the output buffer and follow it with a new-line character.
 * @author Peter Goodman
 */
function outln() {
	$args = func_get_args();
	OutputBuffer::$buffers['out'] .= implode("\n", $args) ."\n";
}

/**
 * Set something to the errors output buffer.
 */
function err() {
	$args = func_get_args();	
	OutputBuffer::$buffers['err'] .= implode('', $args);
}

/**
 * Stop output. Analogous to PHP's exit() function, except that instead of
 * exiting it throws an exception to flush the output buffer.
 * @author Peter Goodman
 */
function stop() {
	throw new FlushBufferException;
}
