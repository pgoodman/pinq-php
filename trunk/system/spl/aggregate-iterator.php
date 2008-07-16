<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Allow iteration over the items of multiple iterators in sequence. This is
 * analagous to merging iterators.
 *
 * @author Peter Goodman
 */
class AggregateIterator implements Iterator {
	
	protected $_pos = 0,
	          $_key = 0,
	          $_current,
	          $_its;
	
	/**
	 * AggregateIterator(Traversable $it, Traversable $it2[, ...])
	 */
	public function __construct(Traversable $it, Traversable $it2) {
		$iterators = func_get_args();
		
		foreach($iterators as $i => $it) {
			
			// make sure all arguments passed are traversible
			if(!($it instanceof Traversable)) {
				throw new InvalidArgumentException(
					"AggregateIterator expects all arguments to extend ".
					"interface [Traversable]."
				);
			}
			
			// get iterator if necessary
			if($it instanceof IteratorAggregate)
				$iterators[$i] = $it->getIterator();
		}
		
		$this->_its = $iterators;
		$this->_current = $this->_its[0];
		$this->_current->rewind();
		$this->findNextIterator();
	}
	
	/**
	 * $i->findNextIterator(void) -> void
	 *
	 * Find the next iterator to use.
	 */
	private function findNextIterator() {
		while(!$this->_current->valid() && isset($this->_its[$this->_pos+1])) {
			$this->_current = $this->_its[++$this->_pos];
			$this->_current->rewind();
		}
	}
	
	/**
	 * $i->key(void) -> int
	 *
	 * Return the current key (iteration).
	 */
	public function key() {
		return $this->_key;
	}
	
	/**
	 * $i->rewind(void) -> void
	 *
	 * Go to the beginning of the first iterator.
	 */
	public function rewind() {
		if($this->_pos || $this->_key) {
			$this->_pos = 0;
			$this->_key = 0;
			$this->_current = $this->_its[0];
			$this->findNextIterator();
		}
	}
	
	/**
	 * $i->next(void) -> void
	 *
	 * Advance to the next item in the current iterator or to the first item
	 * in the next non-empty iterator.
	 */
	public function next() {
		$this->_key++;
		$this->_current->next();
		$this->findNextIterator();
	}
	
	/**
	 * $i->valid(void) -> bool
	 *
	 * Check if we can continue to iterate.
	 */
	public function valid() {
		return isset($this->_its[$this->_pos]) && $this->_current->valid();
	}
	
	/**
	 * $i->current(void) -> mixed
	 *
	 * Return the current item of the current iterator.
	 */
	public function current() {
		return $this->_current->current();
	}
}
