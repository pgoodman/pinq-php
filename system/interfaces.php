<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Simple interface for a class that can parse input into some intermediate
 * form.
 *
 * @author Peter Goodman
 */
interface Parser {
	public function parse($input);
}

/**
 * Interface for classes requiring the __toString() method, ie: classes that
 * can be printed onto the screen.
 *
 * @author Peter Goodman
 */
interface Printer {
	public function __toString();
}

/**
 * Interface for a gateway to a data source. A gateway is what brings together
 * models and data sources.
 *
 * @author Peter Goodman
 */
interface Gateway {
	public function get($query, array $args = array());
	public function delete($what, array $args = array());
	public function insert($query, array $args = array());
	public function update($query, array $args = array());
	public function createRecord(array $data = array());
	public function __get($model_name);
	public function __call($model_name, array $select = array(ALL));
}

interface Factory {
	static public function factory();
}

/**
 * Interface for a class with overloadable properties.
 *
 * @author Peter Goodman
 */
interface Object {
	
	/**
	 * $o->__get(string $key) <==> $o->$key
	 */
	public function __get($key);
	
	/**
	 * $o->__set(string $key, mixed $val) <==> $o->$key = $val
	 */
	public function __set($key, $val);
	
	/**
	 * $o->__isset(string $key) <==> isset($o->$key)
	 */
	public function __isset($key);
	
	/**
	 * $o->__unset(string $key) <==> unset($o->$key)
	 */
	public function __unset($key);
}

/**
 * Interface for a class that compiles a data structure into another form of
 * data.
 *
 * @author Peter Goodman
 */
interface Compiler {
	
	/**
	 * $c->compile(int $flags) -> mixed
	 */
	public function compile($flags = 0);
}

/**
 * An interface for any type of filtering class, state machine, etc. This is
 * iterator-like insofar as something that's stateful needs to internally be
 * able to move back and forth between states and even be able to rewind.
 */
/*interface Stateful {	
	public function valid($state);
	public function current();
	public function next();
	public function prev();
}*/
