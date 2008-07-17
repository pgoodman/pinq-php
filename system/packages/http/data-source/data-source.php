<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class PinqHttpRequest implements DataSource {
	
	/**
	 * $d->open(string $url) -> mixed resource
	 *
	 * Open a connection to a data source.
	 */
	public function open($url) {
		
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			
			CURLOPT_CONNECTTIMEOUT => 10, // TODO
			CURLOPT_TIMEVALUE => time(),
			
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_AUTOREFERER => TRUE,
			CURLOPT_FORBID_REUSE => TRUE,
			CURLOPT_FRESH_CONNECT => TRUE,
		));
	}
	
	public function close() { }
	public function select($query, array $args = array()) { }
	public function update($query, array $args = array()) { }
}