<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An instance of a SQLite database connection.
 * @author Peter Goodman
 */
class PinqSqliteDbResource extends PinqDbResource {
	
	protected $conn;
	
	/**
	 * Constructor, Connect to the database. For SQLite, the host config
	 * setting is seen as the database.
	 */
	public function connect($host, $user = '', $pass = '', $db = '') {
		
		if(!class_exists('SQLiteDatabase', FALSE)) {
			throw new PinqDatabaseException(
				"SQLite does not appear to be installed or configured  on ".
				"this server."
			);
		}
		// if the database doesn't exist, create it
		if(!file_exists($host)) {
			
			if(!($fp = fopen($host, "w"))) {
				throw new PinqDatabaseException(
					"SQLite database file [{$host}] could not be ".
					"automatically created. Please check folder permissions."
				);
			}
			
			fclose($fp);
			@chmod($host, 0666);
		}
		
		// the db file isnt readable/writable
		if(!is_readable($host) || !is_writable($host)) {
			throw new PinqDatabaseException(
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
			throw new PinqDatabaseException(
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
	
	protected function queryError($query, $error) {
		
		$last_error = $this->error();

		if(0 != strcmp($last_error, $error))
			$error .= "\n{$last_error}";
		
		// usually the result of a malformed query. This won't reveal too much
		// assuming proper use of the $args array because the query has not
		// had its substitutes replaced
		throw new PinqDatabaseException(
			"The following database query failed:\n".
			"<pre>{$query}</pre>\n".
			"The error reported was:\n".
			"<pre>{$error}</pre>"
		);
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
		
		return $this->_packages->loadNew('record-iterator',	array($result));
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
	
	/**
	 * Get the query compiler.
	 */
	public function getQueryCompiler(Dictionary $models, 
	               PinqModelRelationalRelations $relations) {
	
		return $this->_packages->load('pql.query-compiler', array(
			$models,
			$relations,
			$this
		));
	}
}
