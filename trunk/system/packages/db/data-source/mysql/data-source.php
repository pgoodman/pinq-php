<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An instance of a MySQL database connection.
 * @author Peter Goodman
 */
class MysqlDatabase extends Database {
	
	protected $conn;
	
	/**
	 * Constructor, Connect to the database.
	 */
	public function __construct($host, $user = '', $pass = '') {
		if(!($this->conn = mysql_connect($host, $user, $pass, FALSE)))
			throw new DatabaseException("Could not connect to the database.");
	}
	
	/**
	 * Open a database, this is a method for DataSource.
	 */
	public function open($name) {
		if(!mysql_select_db($name, $this->conn)) {
			throw new DatabaseException(
				"Could not connect to the database [{$name}]. ".
				$this->error()
			);
		}
	}
	
	/**
	 * Close the database connection.
	 */
	public function close() {
		mysql_close($this->conn);
	}
	
	/**
	 * Query a MySQL database and return a result.
	 */
	protected function query($query, array $args) {
		$result = mysql_query(
			$this->substituteArgs($query, $args), 
			$this->conn
		);
		
		// usually the result of a malformed query. This won't reveal too much
		// assuming proper use of the $args array because the query has not
		// had its substitutes replaced
		if(FALSE === $result) {
			throw new DatabaseException(
				"The following database query failed:".
				"<pre>{$query}</pre>".
				"The error reported was: ".
				"<pre>". $this->error() ."</pre>"
			);
		}
		
		return $result;
	}
	
	/**
	 * Get the last error from mysql.
	 */
	protected function error() {
		return mysql_error($this->conn);
	}
	
	/**
	 * Get the last insert id from mysql.
	 */
	protected function insertId() {
		return mysql_insert_id($this->conn);
	}
	
	/**
	 * Quote a string for insertion into a query.
	 */
	public function quote($str) {
		return mysql_real_escape_string($str);
	}
	
	/**
	 * Get the number of rows affected by the last insert/update/delete
	 */
	protected function affectedRows() {
		return mysql_affected_rows($this->conn);
	}
	
	/**
	 * Get the number of rows in a result set.
	 */
	public function numRows($result) {
		return mysql_num_rows($result);
	}
	
	/**
	 * Seek somewhere in a result.
	 */
	public function seekResult($result, $offset) {
		mysql_data_seek($result, $offset);
	}
	
	/**
	 * Fetch a row from the database.
	 */
	public function fetchRow($result) {
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Return a record iterator.
	 */
	public function getRecordIterator($result) {
		return new MysqlRecordIterator($result);
	}
}
