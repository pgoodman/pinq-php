<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An instance of a MySQL database connection.
 * @author Peter Goodman
 */
class MysqlDataSource extends PinqDbResource {
	
	protected $conn;
	
	/**
	 * Constructor, Connect to the database.
	 */
	public function connect($host, $user = '', $pass = '', $name = '') {
		if(!($this->conn = mysql_connect($host, $user, $pass, FALSE)))
			throw new PinqDbException("Could not connect to the database.");
		
		if(!mysql_select_db($name, $this->conn)) {
			throw new PinqDbException(
				"Could not connect to the database [{$name}]. ".
				$this->error()
			);
		}
	}
	
	/**
	 * Close the database connection.
	 */
	public function disconnect() {
		mysql_close($this->conn);
	}
	
	/**
	 * Query a MySQL database and return a result.
	 */
	protected function select($query) {
		$result = mysql_query($query, $this->conn);
		
		if(FALSE === $result)
			$this->queryError($query, $error);
		
		return $result;
	}
	
	/**
	 * Query a MySQL database and return a result.
	 */
	protected function update($query) {
		$result = mysql_query($query, $this->conn);
		
		if(FALSE === $result)
			$this->queryError($query, $error);
		
		return is_resource($result);
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
	protected function getInsertId() {
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
