<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An instance of a SQLite database connection.
 * @author Peter Goodman
 */
class PinqDbSqliteResource extends PinqDbResource {
	
	protected $conn;
	
	/**
	 * Constructor, Connect to the database. For SQLite, the host config
	 * setting is seen as the database.
	 */
	public function connect($host, $user = '', $pass = '', $db = '') {
		
		if(!class_exists('SQLiteDatabase', FALSE)) {
			throw new PinqDbException(
				"SQLite does not appear to be installed or configured  on ".
				"this server."
			);
		}
		// if the database doesn't exist, create it
		if(!file_exists($host)) {
			
			if(!($fp = fopen($host, "w"))) {
				throw new PinqDbException(
					"SQLite database file [{$host}] could not be ".
					"automatically created. Please check folder permissions."
				);
			}
			
			fclose($fp);
			@chmod($host, 0666);
		}
		
		// the db file isnt readable/writable
		if(!is_readable($host) || !is_writable($host)) {
			throw new PinqDbException(
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
			throw new PinqDbException(
				"Unable to connect to the SQLite database [{$error}]."
			);
		}
	}
	
	/**
	 * Close the database connection.
	 */
	protected function disconnect() {
		unset($this->conn);
	}
	
	/**
	 * Query the database and return a result.
	 */
	protected function select($query) {
		
		$error = '';
		
		echo '<pre>'. $query .'</pre>';
		
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
	protected function update($query) {		
		
		$error = '';
		
		echo '<pre>'. $query .'</pre>';
		
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
	protected function getInsertId() {
		return $this->conn->lastInsertRowid();
	}
	
	/**
	 * Quote a string for insertion into a query.
	 */
	public function quote($str) {
		return sqlite_escape_string($str);
	}
}
