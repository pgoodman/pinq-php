<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class Database implements DataSource {
	
	/**
	 * The bare bones of what's needed to abstract a database.
	 */
	abstract public function __construct($host, $user = '', $pass = '');
	abstract protected function query($query);
	abstract protected function error();
	abstract protected function insertId();
	abstract protected function quote($str);
	abstract protected function affectedRows();
	abstract protected function getRecordIterator($result);
	
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
		$result = $this->query($this->substituteArgs($query, $args));
		
		if(!is_resource($result))
			return NULL;
		
		return $this->getRecordIterator($result);
	}
	
	/**
	 * Insert/update/delete rows from a database.
	 */
	final public function update($query, array $args = array()) {
		return (bool)$this->query($this->substituteArgs($query, $args));
	}
	
	/**
	 * Given a SQL query statement, replace all question marks (?) in it with 
	 * their respecitve values in the $args array. At the same time, make all 
	 * values in the $args array safe for insertion to the query.
	 */
	final protected function substituteArgs($stmt = '', array $args = array()) {
		
		$count = substr_count($stmt, '?');
		
		// don't need to substitute anything in
		if(!$count)
			return $stmt;
		
		// make sure our args array isn't too big
		if($count < count($args))
			$args = array_slice($args, 0, $count);
		
		// finalize the args arra
		$args = array_merge(array_fill(0, $count, 'NULL'), $args);
		
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
		return str_replace('-%-', '%', vsprintf($stmt, $vars));
	}
}
