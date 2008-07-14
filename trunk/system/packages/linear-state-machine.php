<?php

/* $Id$ */

/**
 * states(void) -> PinqLinearStateMachine
 *
 * Return a new linear state machine.
 *
 * @author Peter Goodman
 */
function states() {
	return call_user_class_array('PinqLinearStateMachine');
}

/**
 * A state machine-like class for validating the order of states. It is linear
 * insofar as a states move in only one direction: forward. The state machine
 * works as a stack and a queue. States are added to the stack, then as they
 * are popped off (a transition), they are put onto the transitions queue. Each
 * state is also a queue, thus allowing for nested states and branching.
 *
 * @author Peter Goodman
 */
class PinqLinearStateMachine extends Stack implements Package, Factory {
	
	static public $_class;

	/**
	 * PinqLinearStateMachine::factory(void) -> PinqLinearStateMachine
	 *
	 * Factory to instantiate an instance of this class.
	 */
	static public function factory() {
		$class = self::$_class;
		return new $class;
	}
	
	protected $full = FALSE, // are we done adding states?
	          $transitions; // the currently available transitions
	
	/**
	 * PinqLinearStateMachine(void)
	 */
	public function __construct() {
		$this->transitions = new Queue;
		$this->operators = new Stack;
	}
	
	/**
	 * $m->pushState(string $id, int $type) -> PinqLinearStateMachine
	 *
	 * Add a state to the state machine.
	 */
	protected function pushState($state, $type) {
		
		if($this->full) {
			throw new InvalidStateException("State machine is full.");
		}
		
		$s = new State;
		$s->type = $type;
		$s->state = $state;
		
		$this->push($s);
		$this->states[(string)$state] = TRUE;
		
		return $this;
	}
	
	/**
	 * $m->getTransitions(void) -> Queue
	 *
	 * Return the transitions of this state machine.
	 */
	public function getTransitions() {
		
		while(!$this->isEmpty())
			$this->pop();
		
		$this->full = TRUE;
		
		return $this->transitions;
	}
	
	/**
	 * $m->pop([string $expected]) -> PinqLinearStateMachine
	 *
	 * Pop the current state off the stack and then add it into one of the
	 * queues. We can optionally supply what we expect the state to be and
	 * if it isn't that then an exception is thrown.
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
	 * $m->to(void) -> PinqLinearStateMachine
	 *
	 * We're done adding a state, pop it off the stack and place it into the
	 * progression.
	 */
	public function to() {
		return $this->pop();
	}
	
	/**
	 * $m->optional(string[]) -> PinqLinearStateMachine
	 *
	 * Set the optional states. This takes a variable number of string 
	 * arguments representing state ids.
	 */
	public function optional() {
		$states = func_get_args();
		return $this->pushStates($states, PinqLinearState::OPTIONAL);
	}
	
	/**
	 * $m->required(string[]) -> PinqLinearStateMachine
	 *
	 * Set the required states. This takes a variable number of string
	 * arguments representing state ids.
	 */
	public function required() {
		$states = func_get_args();
		return $this->pushStates($states, PinqLinearState::REQUIRED);
	}
	
	/**
	 * $m->pushStates(array $states, int $type) -> PinqLinearStateMachine
	 *
	 * Push a set of states onto the stack.
	 */
	protected function pushStates(array $states, $type) {
				
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
	 * $m->pushBranch(array $states[, int $branch_type[, int $state_type]])
	 * -> PinqLinearStateMachine
	 *
	 * Create a branch state.
	 */
	protected function pushBranch(array $states, 
	                                    $branch_type = PinqLinearState::OPTIONAL, 
	                                    $state_type = PinqLinearState::OPTIONAL) {
		
		// make sure we have at least one optional state to add
		if(empty($states)) {
			throw new InvalidStateException(
				"Expected state list for optional states."
			);
		}
		
		// push a new branch onto the stack, then add the items to the
		// branch
		$this->pushState('__branch__', PinqLinearState::BRANCH | $branch_type);
		
		// push the states onto the stack then queue them into the branch
		foreach($states as $state)
			$this->pushState($state, $state_type)->pop();
		
		return $this;
	}

	/**
	 * $m->optionalXor(string[]) -> PinqLinearStateMachine
	 *
	 * A set of optional states that are exclusive. We don't pop the branch
	 * off of the stack because it ought be popped off by the programmer with
	 */
	public function optionalXor() {
		$states = func_get_args();
		
		return $this->pushBranch(
			$states, 
			PinqLinearState::OPTIONAL,
			PinqLinearState::OPTIONAL
		);
	}
	
	/**
	 * $m->requiredXor(string[]) -> PinqLinearStateMachine
	 *
	 * Singular branch to, for symmetry.
	 */
	public function requiredXor() {
		$states = func_get_args();
		
		return $this->pushBranch(
			$states, 
			PinqLinearState::REQUIRED,
			PinqLinearState::OPTIONAL
		);
	}
	
	/**
	 * $m->orBranchTo(string $state[, int $type]) -> PinqLinearStateMachine
	 *
	 * If the top item on the stack is a branch, add this state to the branch.
	 * If it's not, throw an invalid state exception.
	 */
	public function orBranchTo($state, $type = PinqLinearState::OPTIONAL) {
		// whoops, stack is empty
		if(0 === count($this))
			throw new InvalidStateException(
				"Expected to find branch on stack. Instead stack is empty."
			);
		
		// we expect the top state on the stack to be a branch
		$branch = $this->top();
		
		// we didn't actually get a branch (as we expecte to)
		if(!($branch->type & PinqLinearState::BRANCH))
			throw new InvalidStateException(
				"Top element on state stack is not Branch state."
			);
		
		return $this->pushState($state, $type);
	}
}

/**
 * Class representing a single state in the state machine.
 *
 * @author Peter Goodman
 * @internal
 */
class PinqLinearState extends Queue {
	
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

/**
 * Maintain a stack of queues to validate incoming states against a state
 * machine.
 *
 * @author Peter Goodman
 */
class PinqLinearStateValidator extends Stack {
	
	/**
	 * PinqLinearStateValidator(PinqLinearStateMachine)
	 */
	public function __construct(PinqLinearStateMachine $machine) {
		$this->push($machine->getTransitions());
	}
	
	/**
	 * $v->valid(string $state) -> bool
	 *
	 * With an incoming state, check if it's valid.
	 */
	public function valid($state) {
		
		// go over the next states. self::next handles entering and exiting
		// branches
		while(NULL !== ($current = $this->fetch())) {
			
			// the state is required
			if($current->type & PinqLinearState::REQUIRED) {
				
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
	
	/**
	 * $v->fetch(void) -> PinqLinearState
	 *
	 * Advance to the next state. Deal with entering and exiting branches as
	 * well.
	 */
	protected function fetch() {
		
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
			if($next->type & PinqLinearState::BRANCH) {
								
				// empty branch, shift it out and continue to the next
				// iteration of this check
				if(0 === count($next)) {
					$top->shift();
					continue;
				}
				
				// the branch isn't empty, push it onto the stack and repeat.
				$this->push($next);
			}
			
			// the first element in the queue on the top of the stack is a
			// normal state. Break out of the loop and return the state.
			break;
		
		// keep going until we find an unused state or until everything is off
		// the stack
		} while(TRUE);
	
		// get the next state
		return $top->shift();
	}
}
