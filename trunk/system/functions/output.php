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
 *
 * @author Peter Goodman
 */
class OutputBuffer {
	
	static public $buffers = array(
		'out' => '',
		'err' => '',
	);
	
	/**
	 * OutputBuffer::clear(string $which) -> void
	 *
	 * Clear one of the output buffer's internal buffers.
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
	 * OutputBuffer::clearAll(void) -> void
	 *
	 * Clear all output buffers.
	 */
	static public function clearAll() {
		self::$buffers = array(
			'out' => '',
			'err' => '',
		);
	}
	
	/**
	 * OutputBuffer::flush(string $which) -> void
	 *
	 * Output and clear a specific output buffer.
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
 * out([string[, string[, ...]]]) -> void
 *
 * Send some data to the 'out' output buffer.
 *
 * @author Peter Goodman
 */
function out() {
	$args = func_get_args();	
	OutputBuffer::$buffers['out'] .= implode('', $args);
}

/**
 * outln([string[, string[, ...]]]) -> void
 *
 * Send some data to the output buffer, separating each datum with a new line.
 *
 * @author Peter Goodman
 */
function outln() {
	$args = func_get_args();
	OutputBuffer::$buffers['out'] .= implode("\n", $args) ."\n";
}

/**
 * err([string[, string[, ...]]]) -> void
 *
 * Set something to the 'err' output buffer.
 *
 * @author Peter Goodman
 */
function err() {
	$args = func_get_args();	
	OutputBuffer::$buffers['err'] .= implode('', $args);
}

/**
 * stop() ! FlushBufferException
 *
 * Stop output. Analogous to PHP's exit() function, except that instead of
 * exiting it throws a FlushBufferException which is caught and used to output
 * to the browser.
 *
 * @author Peter Goodman
 */
/*function stop() {
	throw new FlushBufferException;
}*/
