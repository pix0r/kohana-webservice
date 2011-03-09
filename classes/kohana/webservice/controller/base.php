<?php

abstract class Kohana_WebService_Controller_Base extends Controller {
	/**
	 * Content to be returned to user
	 * @var array
	 */
	protected $content = null;

	/**
	 * Input format (JSON/XML/etc)
	 */
	protected $input_format = null;

	/**
	 * Output format (HTML/JSON/XML/etc)
	 */
	protected $output_format = null;

	/**
	 * Map of HTTP verbs to controller actions
	 */
	protected $_action_map = array(
		'GET'    => 'index',
		'PUT'    => 'update',
		'POST'   => 'create',
		'DELETE' => 'delete',
	);

	/**
	 * Save currently requested action
	 */
	protected $_action_requested = '';

	/**
	 * List of all available formats and their MIME types
	 */
	protected $_format_map = array(
		'html'			=> array('application/html', 'application/xhtml', 'text/html'),
		'json'			=> array('application/json', 'text/json'),
		'xml'			=> array('application/xml', 'text/xml'),
		'php'			=> array('application/vnd.php.serialized'),
		'form'			=> array('application/x-www-form-urlencoded'),
	);

	/**
	 * Formats allowed for input
	 */
	protected $_allowed_input_formats = array(
		'json',
		'form',
		'xml',
	);

	/**
	 * Formats allowed for output
	 */
	protected $_allowed_output_formats = array(
		'html',
		'json',
		'xml',
		'php',
	);

	/**
	 * Determine output format & map HTTP verb to action
	 */
	public function before() {
		$this->output_format = $this->read_output_format();
		$this->remap_request_action();
	}

	/**
	 * Find and render the view for the specified output format
	 */
	public function after() {
		$view = $this->view_for_format($this->output_format);
		if ($view) {
			$view->content = $this->content;
			$view->uri = $this->request->uri();
			echo $view->render();
		} else {
			throw new WebService_Exception(500, "An error has occurred");
		}
	}

	/**
	 * Translate HTTP verb into a request action
	 *
	 * Original request action stored in $this->_action_requested
	 */
	protected function remap_request_action() {
		$this->_action_requested = $this->request->action();

		if (!isset($this->_action_map[$this->request->method()])) {
			$this->request->action('invalid');
		} else {
			$this->request->action($this->_action_map[$this->request->method()]);
		}
	}

	/**
	 * Read and return request data
	 *
	 * Depending on the input type (Content-Type header, overridden by query
	 * string __input_format), this can be raw or parsed POST data.
	 */
	protected function request_data() {
		static $request_data = NULL;

		if ($request_data !== NULL) {
			return $request_data;
		}

		/**
		 * Determine if format was explicitly set in request parameters
		 */
		if ($format = $this->request->param('input_format', null)) {
		} elseif (isset($_GET['__input_format'])) {
			$format = $_GET['__input_format'];
		} elseif (isset($_SERVER['CONTENT_TYPE'])) {
			$format = $this->format_for_mime_type($_SERVER['CONTENT_TYPE']);
		}

		/**
		 * Verify that specified format is allowed for input
		 */
		if (!empty($format) && !in_array($format, $this->_allowed_input_formats)) {
			$format = null;
		}

		if (empty($format)) {
			$format = $this->_allowed_input_formats[0];
		}

		$this->input_format = $format;

		switch ($format) {
		case 'form':
			if ($this->request->action() == 'update') {
				// PHP doesn't set up $_POST for PUT requests
				$request_data = array();
				$input = file_get_contents('php://input');
				parse_str($input, $request_data);
			} else {
				$request_data = $_POST;
			}
			break;
		default:
			if (isset($_REQUEST['__data'])) {
				$request_data = $_REQUEST['__data'];
			} else {
				$request_data = file_get_contents('php://input');
			}
			if ($request_data === false) {
				throw new WebService_Exception(500, "error_reading_input");
			}
			break;
		}

		return $request_data;
	}

	/**
	 * Return key for a given MIME type
	 */
	protected function format_for_mime_type($type_array, $whitelist = null) {
		static $mime_map = array();

		if (!is_array($type_array))
			$type_array = array($type_array);

		if (!count($mime_map)) {
			foreach ($this->_format_map as $f => $mime_types) {
				foreach ($mime_types as $t) {
					$mime_map[$t] = $f;
				}
			}
		}

		foreach ($type_array as $mime) {
			// Skip anything after a comma
			$mime = preg_replace('/,.*/', '', $mime);
			if (isset($mime_map[$mime])) {
				if (!is_array($whitelist) || in_array($mime, $whitelist)) {
					return $mime_map[$mime];
				}
			}
		}

		return null;
	}

	/**
	 * Determine output format from Accept: header or format paremeter.
	 *
	 * Format parameter is normally read from requested file extension.
	 */
	protected function read_output_format() {
		$format = null;

		/**
		 * Determine if format was explicitly set in request parameters
		 */
		if (!($format = $this->request->param('format', null))) {
			$format = $this->format_for_mime_type($this->request->accept_type(), $this->_allowed_output_formats);
		}

		if (empty($format)) {
			// Default format
			$format = $this->_allowed_output_formats[0];
		}

		if (empty($format) || !in_array($format, $this->_allowed_output_formats)) {
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
		$c = strtolower($this->request->controller());
		$a = strtolower($this->request->action());
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

	/**
	 * Generic handler for invalid HTTP verb
	 */
	public function action_invalid() {
		$this->request->response()->status(405);
		$this->request->headers('Allow', implode(', ', array_keys($this->_action_map)));
	}
}

