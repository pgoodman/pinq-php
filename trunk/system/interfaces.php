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
 * Interface for something that will load and configure something else.
 */
interface Loader {
	public function &load($key, array $context = array());
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
 * An interface for data sources.
 * @author Peter Goodman
 */
interface DataSource {
	
	// open a connection to a data source
	public function open($name);
	
	// close the connection to a data source
	public function close();
	
	// select rows from a data source
	public function select($query, array $args = array());
	
	// insert/update/delete/replace rows in a data source
	public function update($query, array $args = array());
}

/**
 * A gateway to the records in a given data source.
 * @author Peter Goodman
 */
abstract class RecordGateway {
	
	// the data source
	protected $ds;
	
	/**
	 * Constructor, bring in the data source.
	 */
	public function __construct(DataSource $ds) {
		$this->ds = $ds;
	}
	
	/**
	 * Compile a query for a specific data source.
	 */
	abstract protected function compileQuery(AbstractQuery $query);
	
	/**
	 * Get a string representation for a datasource-specic query.
	 */
	protected function getQuery($query) {
		if($query instanceof AbstractQuery)
			return $this->compileQuery($query);
		
		return (string)$query;
	}
	
	
}

/**
 * A set of data records.
 * @author Peter Goodman
 */
abstract class RecordIterator implements Countable, SeekableIterator {
	
	protected $offset = 0,
	          $limit,
	          $key;
	
	public function __construct() {
		$this->key = $this->offset;
		$this->limit = $this->count();
	}
	
	/**
	 * Seek to a specific index in the iterator.
	 */
	public function seek($key) {
		
		// make sure the key is in the right place
		if($key < $this->offset || $key >= $this->limit) {
			throw new OutOfBoundsException(
				"Could not access row [{$key}] of record set."
			);
		}
		
		$this->key = $key;
	}
	
	/**
	 * Return the current key.
	 */
	public function key() {
		return $this->key;
	}
	
	/**
	 * Rewind the record set.
	 */
	public function rewind() {
		$this->seek($this->offset);
	}
	
	/**
	 * Is the current row valid?
	 */
	public function valid() {
		return $this->key < $this->limit;
	}
	
	/**
	 * Move to the next row.
	 */
	public function next() {
		$this->key++;
	}
	
	/**
	 * Make this iterator only go over a slice of the result set. Limit does
	 * not act as an offset from the offset, it is the right-side bracket.
	 */
	public function limit($offset, $limit = NULL) {
		
		// make sure that the limit is within the proper range
		if(NULL !== $limit) {
			$count = $this->count();
			$limit = (int)$limit;
			$this->limit = ($limit <= $count) ? $limit : 
			               ($limit < 0) ? 0 : $count;
		}
		
		// make sure the offset is within the proper range
		$offset = (int)$offset;
		$offset = ($offset < 0) ? 0 : 
		          ($offset > $this->limit) ? $this->limit : $offset;
		
		$this->offset = $offset;
	}
}

/**
 * An iterator to handle an inner record iterator. This has the same functionality
 * as an IteratorIterator, although unfortunately PHP doesn't support multiple
 * inheritance.
 * @author Peter Goodman
 */
abstract class OuterRecordIterator extends RecordIterator implements OuterIterator {
	
	protected $it;
	
	/**
	 * Constructor, bring in the record iterator.
	 */
	public function __construct(RecordIterator $record_set) {
		parent::__construct();
		$this->it = $record_set;
	}
	
	/**
	 * Get the inner record iterator.
	 */
	public function getInnerIterator() {
		return $this->it;
	}
	
	/**
	 * RecordIterator methods...
	 */
	public function current() { return $this->it->current(); }
	public function key() { return $this->it->key(); }
	public function next() { return $this->it->next(); }
	public function valid() { return $this->it->valid(); }
	public function seek($key) { return $this->it->seek($key); }
	public function limit($offset, $limit = NULL) { 
		return $this->it->limit($offset, $limit);
	}
}

/**
 * A data record.
 */
abstract class Record extends Dictionary {
	abstract public function save();
	abstract public function delete();
}

/**
 * A record holding a record.
 */
abstract class OuterRecord extends Record {
	
	// and instance of a Record class
	protected $record;
	
	/**
	 * Bring in the record to hold.
	 */
	public function __construct(Record $record) {
		$this->record = $record;
	}
	
	/**
	 * Get the record that this outer record holds.
	 */
	public function getInnerRecord() {
		return $this->record;
	}
	
	/**
	 * Record methods.
	 */
	public function offsetGet($key) { return $this->record->offsetGet($key); }
	public function offsetSet($key, $val) { 
		return $this->record->offsetSet($key, $val); 
	}
	public function offsetExists($key) { 
		return $this->record->offsetExists($key); 
	}
	public function offsetUnset($key) {
		return $this->record->offsetUnset($key);
	}
}

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
