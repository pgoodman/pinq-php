<?php

!defined('DIR_APPLICATION') && exit();

class ContentTagsDefinition extends DatabaseModelDefinition {
	
	public function describe() {
		
		$this->setInternalName('www_ContentTags');
		$this->ContentId = int(11);
		$this->TagId = int(11);
		
		$this->ContentId->mapsTo('content', 'Id');
		$this->TagId->mapsTo('tags', 'Id');
	}
}
