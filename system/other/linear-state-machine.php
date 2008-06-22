<?php

/* $Id$ */

/**
 * A state machine-like class for validating the order of states. It is linear
 * insofar as a states move in only one direction: forward. The state machine
 * works as a stack and a queue. States are added to the stack, then as they
 * are popped off (a transition), they are put onto the transitions queue. Each
 * state is also a queue, thus allowing for nested states and branching.
 * @author Peter Goodman
 */
class LinearStateMachine extends Stack {
	
	public $transitions; // the currently available transitions
	private $full = FALSE; // are we done adding states?
	
	/**
	 * Constructor, set up the state transitions.
	 */
	public function __construct() {
		$this->transitions = new Queue;
		$this->operators = new Stack;
	}
	
	/**
	 * Add a state.
	 * @internal
	 */
	private function pushState($state, $type) {
		
		if($this->full)
			throw new InvalidStateException("State machine is full.");
		
		$s = new State;
		$s->type = $type;
		$s->state = $state;
		
		$this->push($s);
		$this->states[(string)$state] = TRUE;
		
		return $this;
	}
	
	/**
	 * Pop the current state off the stack and then add it into one of the
	 * queues.
	 */
	public function pop($expected = NULL) {
		try {
			$state = parent::pop(NULL);
			
			if($expected && $state->state != $expected) {
				throw new InvalidStateException(
					"Invalid state was popped off stack. Found ".
					"[{$state->state}] but expected [{$expected}]."
				);
			}
			
			$where = (0 == count($this)) ? $this->transitions : $this->top();
			$where->push($state);
		
		// we ignore the exception because, when using optional functions that
		// take in multiple parameters, we won't even be adding to the stack
		// and so pop might return an exception.
		} catch(StackException $e) { }
		
		return $this;
	}
	
	/**
	 * We're done adding a state, pop it off the stack and place it into the
	 * progression.
	 */
	public function to() {
		return $this->pop();
	}
	public function __get($operator) {
		return $this->pop();
	}
	
	/**
	 * We're done.
	 */
	public function end() {
		
		// pop anything left off the stack
		while(count($this) > 0)
			$this->pop();
		
		$this->full = TRUE;
		return $this;
	}
	
	/**
	 * Set the optional states.
	 */
	public function optional() {
		$states = func_get_args();
		return $this->pushStates($states, LinearState::OPTIONAL);
	}
	
	/**
	 * Set the required states.
	 */
	public function required() {
		$states = func_get_args();
		return $this->pushStates($states, LinearState::REQUIRED);
	}
	
	/**
	 * Push a set of states onto the stack.
	 */
	private function pushStates(array $states, $type) {
				
		// make sure we have at least one optional state to add
		if(empty($states))
			throw new InvalidStateException(
				"Expected state list for optional states."
			);
		
		// we want to do one fewer pops than the number of states we have
		// put on the stack.
		$this->pushState(array_shift($states), $type);
		foreach($states as $state) {
			$this->pop();
			$this->pushState($state, $type);
		}
		
		return $this;
	}

	/**
	 * Create a branch.
	 */
	private function pushBranch(array $states, 
								$branch_type = LinearState::OPTIONAL, 
								$state_type = LinearState::OPTIONAL) {
		
		// make sure we have at least one optional state to add
		if(empty($states))
			throw new InvalidStateException(
				"Expected state list for optional states."
			);
		
		// push a new branch onto the stack, then add the items to the
		// branch
		$this->pushState('__branch__', LinearState::BRANCH | $branch_type);
		
		// push the states onto the stack then queue them into the branch
		foreach($states as $state)
			$this->pushState($state, $state_type)->pop();
		
		return $this;
	}

	/**
	 * A set of optional states that are exclusive. We don't pop the branch
	 * off of the stack because it ought be popped off by the programmer with
	 * 
	 */
	public function optionalXor() {
		$states = func_get_args();
		
		return $this->pushBranch(
			$states, 
			LinearState::OPTIONAL,
			LinearState::OPTIONAL
		);
	}
	
	/**
	 * Singular branch to, for symmetry.
	 */
	public function requiredXor($state) {
		$states = func_get_args();
		
		return $this->pushBranch(
			$states, 
			LinearState::REQUIRED,
			LinearState::OPTIONAL
		);
	}
	
	/**
	 * If the top item on the stack is a branch, add this state to the branch.
	 * If it's not, throw an invalid state exception.
	 */
	public function orBranchTo($state, $type = LinearState::OPTIONAL) {
		// whoops, stack is empty
		if(0 === count($this))
			throw new InvalidStateException(
				"Expected to find branch on stack. Instead stack is empty."
			);
		
		// we expect the top state on the stack to be a branch
		$branch = $this->top();
		
		// we didn't actually get a branch (as we expecte to)
		if(!($branch->type & LinearState::BRANCH))
			throw new InvalidStateException(
				"Top element on state stack is not Branch state."
			);
		
		return $this->pushState($state, $type);
	}
}

/**
 * A class representing a single state in the state machine.
 * @author Peter Goodman
 * @internal
 */
class State extends Queue {
	
	// different types of states available. Note: no end/terminal state type
	// is required. Note: the validity of a series of states is contingent on
	// all base level required transitions being executed. 
	const OPTIONAL = 1,
		  REQUIRED = 2,
		  BRANCH = 4,
		  EXCLUSIVE = 8,
		  CONTINUES = 16;
	
	public $type,
		   $state,
		   $used = 0,
		   $max_uses = -1;
}
