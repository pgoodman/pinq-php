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
		list($sm, $ss) = explode(' ', microtime());
		$query = $this->substituteArgs($query, $args);
		
		out('<pre>', $query, '</pre>');
		list($em, $es) = explode(' ', microtime());
		out('<pre>', 'Query argument substitution time:', ($em + $es) - ($sm + $ss), '</pre>');
		
		list($sm, $ss) = explode(' ', microtime());
		$result = mysql_query($query, $this->conn);
		list($em, $es) = explode(' ', microtime());
		out('<pre>', 'Query time:', ($em + $es) - ($sm + $ss), '</pre>');
		
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
	 * Get the record set class.
	 */
	protected function getRecordIterator($result) {
		return new MysqlDatabaseRecordIterator($result);
	}
	
	/**
	 * Get the number of rows affected by the last insert/update/delete
	 */
	protected function affectedRows() {
		return mysql_affected_rows($this->conn);
	}
}
