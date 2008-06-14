<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Simple interface for all parsers.
 * @author Peter Goodman
 */
interface Parser {
	public function parse($input);
}

/**
 * Simple interface for all printers (things that generate output).
 * @author Peter Goodman
 */
interface Printer {
	public function __toString();
}

/**
 * Interface for a package.
 */
interface Package {
	
}
interface ConfigurablePackage extends Package {
	static public function configure(Loader $loader, Loader $config, array $args);
}

/**
 * An interface for a class that can be stored in one form or another.
 */
/*interface Storable extends Serializable {
	static public function __set_state();
}*/

/**
 * Handle compiling (translating) one form into another.
 * @author Peter Goodman
 */
abstract class Compilable {
	private $buffer = "";
		
	public function compile() {
		return $this->buffer;
	}
	
	public function buffer($input = "") {
		$this->buffer .= $input;
	}
}

/**
 * Interface specifying the ability to compose the elements of one instance
 * of the same class with itself.
 */
/*
interface Composable {
	public function append(Composable $obj);
	public function extend(Composable $obj);
}*/

/**
 * An interface for any type of filtering class, state machine, etc. This is
 * iterator-like insofar as something that's stateful needs to internally be
 * able to move back and forth between states and even be able to rewind.
 */
interface Stateful {	
	public function valid($state);
	public function current();
	public function next();
	public function prev();
}

/**
 * A continuation.
 */
/*
interface Continuation {
	
}*/

/**
 * Interface signalling that something can be cached.
 */
/*interface Cacheable {
	
}*/


/**
 * Interfaces for observers/dispatchers.
 */
/*
interface Subject {
	public function receive(array $arguments = array());
}
interface Dispatcher {
	public function send($message);
}*/
