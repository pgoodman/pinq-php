<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class Database implements DataSource {
	
	/**
	 * The bare bones of what's needed to abstract a database.
	 */
	abstract public function __construct($host, $user = '', $pass = '');
	abstract protected function query($query, array $args);
	abstract protected function error();
	abstract protected function insertId();
	abstract public function quote($str);
	abstract protected function affectedRows();
	abstract public function numRows($result);
	abstract public function getRecordIterator($result);
	
	/**
	 * Close the database connection.
	 */
	final public function __destruct() {
		$this->close();
	}
	
	/**
	 * Select rows from the database.
	 */
	final public function select($query, array $args = array()) {
		$result = $this->query($query, $args);		
		return $result;
	}
	
	/**
	 * Insert/update/delete rows from a database.
	 */
	final public function update($query, array $args = array()) {
		return (bool)$this->query($query, $args);
	}
	
	/**
	 * Given a SQL query statement, replace all question marks (?) in it with 
	 * their respecitve values in the $args array. At the same time, make all 
	 * values in the $args array safe for insertion to the query.
	 *
	 * TODO: currently this function does not support multiple instances of
	 *       the same keyed substitution in the query.
	 */
	final protected function substituteArgs($stmt = '', array $args = array()) {
		
		if(empty($args))
			return $stmt;
		
		// the arguments array is associative. assume then that the substitutes
		// in the query are also associative and turn them into question marks
		if(is_string(key($args))) {
			
			$key_pattern = '~:([^:\s\b\W]+)~';
			$matches = array();
			
			if(!preg_match_all($key_pattern, $stmt, $matches))
				return $stmt;
			
			// replace all key patterns with question marks
			$stmt = preg_replace($key_pattern, '?', $stmt);
			
			// the intersection
			$intersection = array_intersect_key(
				$args, 
				array_flip($matches[1])
			);
			
			// crap, the args passed don't have all of the proper keys
			if(count($intersection) < count($matches[1])) {
				throw new UnexpectedValueException(
					"Argument passed to database query function expected ".
					"that the following keys be present: [". 
					implode(',', $matches[1]) ."]. Not all keys are present."
				);
			}
			
			// what we are doing is taking the $args array, moving the keys
			// into the order that they appear in in $stmt, matching the
			// values to those keys, then dumping the keys so that we can
			// easily substitute the new args in for question marks.
			$args = array_values(array_combine(
				$matches[1], 
				$intersection
			));
		}
		
		// how many of these are we working with?
		$count = substr_count($stmt, '?');
		
		// don't need to substitute anything in
		if(!$count)
			return $stmt;
		
		// make sure our args array isn't too big
		if($count < count($args))
			$args = array_slice($args, 0, $count);
		
		// finalize the args array by padding its end with NULL's if there
		// aren't enough items in it
		if(0 < ($padding = $count - count($args)))
			$args = array_merge($args, array_fill(0, $padding, NULL));
				
		// if we're dealing with a SQL LIKE statement then we don't want to
		// risk overwriting anything within them
		$stmt = str_replace(array('%', '?'), array('-%-', '%s'), $stmt);
		
		// format the incoming arguments for insertion into the query
		foreach($args as $key => $val) {
			
			// if this is not an integer value, put quotes
			// around it
			if(is_int($val) || is_float($val) || is_numeric($val)) {
				if(ctype_digit($val)) {
					$args[$key] = (string)(int)$val;
				} else {
					$args[$key] = (string)(float)$val;
				}
			
			// booleans, convert to int
			} else if(is_bool($val)) {
				$args[$key] = (int)$val;
			
			// null values
			} else if(is_null($val) || strtoupper($val) == 'NULL') {
				$args[$key] = 'NULL';
			
			// just escape it
			} else {
				$args[$key] = "'". $this->quote($val) ."'";
			}
		}
		
		// sub in the variables, fix the %'s, and return
		return str_replace('-%-', '%', vsprintf($stmt, $args));
	}
}
