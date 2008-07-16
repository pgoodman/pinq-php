<?php

abstract class DatabaseModelDefinition extends RelationalModelDefinition {
	
	protected function getDefaultGatewayClass() {
		return 'DatabaseModelGateway';
	}

	protected function getDefaultRecordClass() {
		return 'DatabaseRecord';
	}	
}