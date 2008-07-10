<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An instance of a SQLite database connection.
 * @author Peter Goodman
 */
class SqliteDataSource extends Database {
	
	protected $conn;
	
	/**
	 * Constructor, Connect to the database. For SQLite, the host config
	 * setting is seen as the database.
	 */
	public function __construct($host) {
		
		if(!class_exists('SQLiteDatabase', FALSE)) {
			throw new DatabaseException(
				"SQLite does not appear to be installed or configured  on ".
				"this server."
			);
		}
		// if the database doesn't exist, create it
		if(!file_exists($host)) {
			
			if(!($fp = fopen($host, "w"))) {
				throw new DatabaseException(
					"SQLite database file [{$host}] could not be ".
					"automatically created. Please check folder permissions."
				);
			}
			
			fclose($fp);
			
			@chmod($host, 0666);
		}
		
		// the db file isnt readable/writable
		if(!is_readable($host) || !is_writable($host)) {
			throw new DatabaseException(
				"SQLite database file [{$host}] cannot be read."
			);
		}
		
		// connect
		$error = NULL;
		try {
			$this->conn = new SQLiteDatabase($host, 0666, $error);
		
		} catch(SQLiteException $e) {
			$error = $e->getMessage();
		}
		
		// connection failed
		if(!empty($error)) {
			throw new DatabaseException(
				"Unable to connect to the SQLite database [{$error}]."
			);
		}
	}
	
	/**
	 * Somewhat useless for sqlite as a database isn't 'selected' as with
	 * other layers.
	 */
	public function open($name) {
		
	}
	
	/**
	 * Close the database connection.
	 */
	public function close() {
		unset($this->conn);
	}
	
	protected function queryError($query, $error) {
		
		$last_error = $this->error();

		if($last_error != $error)
			$error .= "\n{$last_error}";
		
		// usually the result of a malformed query. This won't reveal too much
		// assuming proper use of the $args array because the query has not
		// had its substitutes replaced
		throw new DatabaseException(
			"The following database query failed:".
			"<pre>{$query}</pre>".
			"The error reported was: ".
			"<pre>{$error}</pre>"
		);
	}
	
	/**
	 * Query the database and return a result.
	 */
	protected function query($query, array $args) {
		
		$error = '';
		$query = $this->substituteArgs($query, $args);
		
		out('<pre>', $query, '</pre>');
		
		$result = $this->conn->query(
			$query,
			SQLITE_ASSOC,
			$error
		);
		
		if(FALSE === $result)
			$this->queryError($query, $error);
		
		return $result;
	}
	
	/**
	 * Perform an update query. This is a result-less query.
	 */
	public function update($query, array $args = array()) {		
		
		$error = '';
		$query = $this->substituteArgs($query, $args);
		
		out('<pre>', $query, '</pre>');
		
		$result = $this->conn->queryExec(
			$query,
			$error
		);
		
		if(FALSE === $result)
			$this->queryError($query, $error);
			
		return TRUE;
	}
	
	/**
	 * Get the last error from mysql.
	 */
	protected function error() {
		return sqlite_error_string($this->conn->lastError());
	}
	
	/**
	 * Get the last insert id from mysql.
	 */
	protected function insertId() {
		return $this->conn->lastInsertid();
	}
	
	/**
	 * Quote a string for insertion into a query.
	 */
	public function quote($str) {
		return sqlite_escape_string($str);
	}
	
	/**
	 * Get the number of rows affected by the last insert/update/delete
	 */
	protected function affectedRows() {
		return 0;
	}
	
	/**
	 * Get the number of rows in a result set.
	 */
	public function numRows($result) {
		return $result->numRows();
	}
	
	/**
	 * Seek somewhere in a result.
	 */
	public function seekResult($result, $offset) {
		$result->seek($offset);
	}
	
	/**
	 * Fetch a row from the database.
	 */
	public function fetchRow($result) {
		return $result->fetch(SQLITE_ASSOC);
	}
	
	/**
	 * Return a record iterator.
	 */
	public function getRecordIterator($result) {
		return new SqliteRecordIterator($result);
	}
	
	/**
	 * Get the query compiler.
	 */
	public function getQueryCompiler(Dictionary $models, 
	                             ModelRelations $relations) {
		
		return new SqliteQueryCompiler(
			$models,
			$relations,
			$this
		);
	}
}
