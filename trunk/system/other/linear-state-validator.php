<?php

/* $Id$ */

require_once DIR_SYSTEM . '/linear-state-machine.php';

/**
 * Maintain a stack of queues to validate incoming states against a state
 * machine.
 */
class LinearStateValidator extends Stack implements Stateful {
	
	protected $machine, // the state machine
			  $current; // the current state
	
	/**
	 * Constructor, bring in a state machine.
	 */
	public function __construct(LinearStateMachine $machine) {
		$this->push($machine->transitions);
	}
	
	/**
	 * With an incoming state, check if it's valid.
	 */
	public function valid($state) {
		
		// go over the next states. self::next handles entering and exiting
		// branches
		while(NULL !== ($current = $this->next())) {
			
			// the state is required
			if($current->type & LinearState::REQUIRED) {
				
				if($state == $current->state)
					return TRUE;
				
				break;
			
			// the state is optional by definition of coming here. Check if
			// the state is the current state, and if so, we've validated,
			// otherwise repeat the check for the next state
			} else if($state == $current->state)
				return TRUE;
		}
		
		return FALSE;
	}
	
	// return the current state
	public function current() {
		return $this->current;
	}
	
	// not supported in the linear model
	public function prev() {
		return NULL;
	}
	
	/**
	 * Advance to the next state. Deal with entering and exiting branches as
	 * well.
	 */
	public function next() {
		
		// go through the stack, pop off used up state queues on it. Push on
		// branch queues.
		$top = NULL;
		do {
			
			// the stack is empty, no states left. Once we've reached the last
			// state, self::$current will remain as the last state reached.
			if(0 === count($this))
				return NULL;
			
			// get the top queue on the stack
			$top = $this->top();
			
			// the current queue on the stack is empty, pop it off and repeat
			if(0 == count($top)) {
				$this->pop();
				continue;
			}
			
			// the current queue on the stack is not empty, get its first
			// state (the next state we might return) without shifting it off
			$next = $top->front();
			
			// the next state is actually a branch. If the branch isn't empty,
			// push it onto the stack. If the branch is empty, take it out of
			// the queue on the top of the stack and repeat. 
			if($next->type & LinearState::BRANCH) {
								
				// empty branch, shift it out and continue to the next
				// iteration of this check
				if(0 === count($next)) {
					$top->shift();
					continue;
				}
				
				// the branch isn't empty, push it onto the stack and repeat.
				$this->push($next);
			
			// the first element in the queue on the top of the stack is a
			// normal state. Break out of the loop and return the state.
			} else
				break;
		
		// keep going until we find an unused state or until everything is off
		// the stack
		} while(1);
	
		// get the next state
		return ($this->current = $top->shift());
	}
}
