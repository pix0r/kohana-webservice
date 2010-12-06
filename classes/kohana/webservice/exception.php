<?php

class Kohana_WebService_Exception extends Kohana_Exception {
	public $status_code = 500;

	public function __construct($status_code, $message, array $vars = null, $code = 0) {
		parent::__construct($message, $vars, $code);
		$this->status_code = $status_code;
	}
}

