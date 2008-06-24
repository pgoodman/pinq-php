<?php

abstract class DatabaseModelDefinition extends ModelDefinition {
	
	protected function getDefaultGatewayClass() {
		return 'DatabaseModelGateway';
	}

	protected function getDefaultRecordClass() {
		return 'DatabaseRecord';
	}	
}