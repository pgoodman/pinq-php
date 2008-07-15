<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Interface with the Akismet API.
 *
 * @author Peter Goodman
 */
class PinqAkismet extends InstantiablePackage {
	
	protected $api_key,
	          $key_verified = FALSE,
	          $blog_url,
	          $user_ip,
	          $user_agent;
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		
		$ci = get_instance();
		
		$this->blog_url = $ci->config->slash_item('base_url');
		$this->user_ip = $ci->input->ip_address();
		$this->user_agent = $ci->input->user_agent();
	}
	
	/**
	 * Verify the API key.
	 */
	public function verify($key) {
		
		$this->api_key = $key;
		$key = urlencode($key);
		$url = urlencode($this->blog_url);
		
		return $this->key_verified = $this->query(
			'rest.akismet.com/1.1/verify-key',
			"key={$key}&blog={$url}"
		);
	}
	
	/**
	 * Validate some content with akismet.
	 */
	public function validate(array $content) {
		
		if(!$this->key_verified)
			return FALSE;
		
		// build up the content to send to akismet
		$content = array_merge($_SERVER, array(
			'blog' => $this->blog_url,
			'user_ip' => $this->user_ip,
			'user_agent' => $this->user_agent,
			'referrer' => NULL,
			'permalink' => NULL,
			'comment_type' => 'comment',
			'comment_author' => 'viagra-test-123',
			'comment_author_email' => NULL,
			'comment_author_url' => NULL,
 			'comment_content' => NULL,
		), $content);
		
		// clear out useless values
		foreach($content as $key => $val) {
			if(empty($val))
				unset($content[$key]);
		}
		
		// is this spam?
		return !$this->query(
			"{$this->api_key}.rest.akismet.com/1.1/comment-check",
			http_build_query($content)
		);
	}
	
	/**
	 * Get a response from akismet for something.
	 * @internal
	 */
	protected function query($url, $data) {
		
		$ch = curl_init();
		
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_FRESH_CONNECT => TRUE, // don't allow caching
			CURLOPT_FORBID_REUSE => TRUE,
			CURLOPT_POST => TRUE,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_POSTFIELDS => $data,
		));
		
		// if it fails the response will be false
		$response = curl_exec($ch);
        curl_close($ch);
		
		// this goes for the key being valid or the comment being spam :P
		if($response == 'valid' || $response == 'true')
			return TRUE;
		
		return FALSE;
	}
}
