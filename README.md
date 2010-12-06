Web service module for Kohana 3.0.x
===================================

This is a simple module providing a groundwork for "RESTful" web services in Kohana 3. Current functionality includes automatic action remapping and multiple output formats (HTML, JSON, XML, PHP).

Usage
-----

* Extend `WebService_Controller_Base` in one of your application controllers.
* Create actions to respond to desired HTTP methods: `action_index` (GET), `action_update` (PUT), `action_create` (POST), `action_delete` (DELETE). Return response data to user via `$content` member variable.
* Optionally customize `$_format_map` to contain your supported list of formats. Default is html, json, xml, php.
* Optionally create custom views for each supported format. Defaults go in `views/webservice/default/<format>.php`.
* Optionally create custom views for your controller. View search path is `views/<controller>/<action>/<format>.php`, `views/<controller>/<default>/<format>.php`, and finally `views/webservice/default/<format>.php`.
* Optionally create a custom route that captures a `<format>` request parameter.

Custom route example:

	Route::set('webservice', '(<controller>(/<id>))(.<format>)')
		->defaults(array(
			'controller' => 'demo',
			'action'     => 'index',
		));


