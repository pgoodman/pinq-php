<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The model gateway is the link between the model layer and the database.
 * @author Peter Goodman
 */
class DatabaseModelGateway extends ModelGateway {
	
	protected $_compiler;
	
	public function __destruct() {
		parent::__destruct();
		
		unset($this->_compiler);
	}
	
	/**
	 * Compile a query.
	 */
	protected function compileQuery(Query $query, $type) {
		
		// cache the compiler
		if($this->_compiler === NULL) {
			$this->_compiler = new DatabaseQueryCompiler(
				$this->_models,
				$this->_relations,
				$this->_ds
			);
		}
		
		// chaneg the query stored in the compiler
		$this->_compiler->setQuery($query);
		
		return $this->_compiler->compile($query, $type);
	}
}