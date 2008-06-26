<?php

/**
 * Default error controller, doesn't actually need to be changed.
 * @author Peter Goodman
 */
class ErrorController extends PinqController {
	public function ANY_403() { set_http_status(403); }
	public function ANY_404() { set_http_status(404); }
	public function ANY_405() { set_http_status(405); }
	public function ANY_500() { set_http_status(500); }
}
