<?php

class DatabaseModelDefinition extends ModelDefinition {
	
	protected function getDefaultGatewayClass() {
		return 'DatabaseModelGateway';
	}

	protected function getDefaultRecordClass() {
		return 'DatabaseRecord';
	}

	protected function getDefaultRecordIteratorClass() {
		return 'DatabaseRecordIterator';
	}
	
}