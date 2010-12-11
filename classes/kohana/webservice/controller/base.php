<?php

abstract class Kohana_WebService_Controller_Base extends Controller {
	/**
	 * Content to be returned to user
	 * @var array
	 */
	protected $content = null;

	protected $_action_map = array(
		'GET'    => 'index',
		'PUT'    => 'update',
		'POST'   => 'create',
		'DELETE' => 'delete',
	);

	protected $_action_requested = '';

	protected $_format_map = array(
		'html'			=> array('application/html', 'application/xhtml', 'text/html'),
		'json'			=> array('application/json', 'text/json'),
		'xml'			=> array('application/xml', 'text/xml'),
		'php'			=> array('application/vnd.php.serialized'),
	);

	public function before() {
		$this->remap_request_action();
	}

	public function after() {
		$format = $this->read_output_format();
		$view = $this->view_for_format($format);
		if ($view) {
			$view->content = $this->content;
			$view->uri = $this->request->uri();
			echo $view->render();
		} else {
			throw new WebService_Exception(500, "An error has occurred");
		}
	}

	protected function remap_request_action() {
		$this->_action_requested = $this->request->action;

		if (!isset($this->_action_map[Request::$method])) {
			$this->request->action = 'invalid';
		} else {
			$this->request->action = $this->_action_map[Request::$method];
		}
	}

	protected function read_output_format() {
		$format = null;

		/**
		 * Determine if format was explicitly set in request parameters
		 */
		if (!($format = $this->request->param('format', null))) {
			/**
			 * Build array of MIME-type mappings
			 */
			$mime_map = array();
			foreach ($this->_format_map as $f => $mime_types) {
				foreach ($mime_types as $t) {
					$mime_map[$t] = $f;
				}
			}
			/**
			 * Scan browser accept types for matching MIME type
			 */
			$browser_accept_types = $this->request->accept_type();
			foreach ($browser_accept_types as $type) {
				if (isset($mime_map[$type])) {
					$format = $mime_map[$type];
					break;
				}
			}
		}
		if (empty($format)) {
			// Default format
			$format = current(array_keys($this->_format_map));
		}
		if (empty($format) || !isset($this->_format_map[$format])) {
			throw new WebService_Exception(406, "No supported accept types found");
		}
		return $format;
	}

	/**
	 * Find view for requested format. Default implementation searches
	 * in these locations:
	 *   views/<controller>/<action>/<format>
	 *   views/<controller>/<format>
	 *   views/webservice/default/<format>
	 *
	 * To increase performance by skipping these directory searches, override
	 * this method and explicitly set the view location.
	 */
	protected function view_for_format($format) {
		$view = null;
		$c = strtolower($this->request->controller);
		$a = strtolower($this->request->action);
		$paths = array(
			"$c".DIRECTORY_SEPARATOR."$a",
			"$c",
			"webservice".DIRECTORY_SEPARATOR."default",
		);
		foreach ($paths as $path) {
			if (Kohana::find_file('views'.DIRECTORY_SEPARATOR.$path, $format)) {
				$view = View::factory("$path".DIRECTORY_SEPARATOR."$format");
				break;
			}
		}
		return $view;
	}

	public function action_invalid() {
		$this->request->status = 405;
		$this->request->headers['Allow'] = implode(', ', array_keys($this->_action_map));
	}
}

