<?php

namespace atREST;

/** RESTful API routing and execution class.
 */

class API
{

	// Constants

	const Tag	= 'API';

	const DirectoryPathPrefix	= 'APIv';
	const UserObjectSuffix		= 'Endpoint';
	const PathRootParameterName = 'API.Root';
	const DefaultPathRoot		= '/api/v';
	const ValidContentType      = 'application/json';
	const MeID 					= 'me';

	// Public Methods

	public static function HandleRequest()
	{
		if (self::$pathRoot) {
			return;
		}

		self::$pathRoot = strtolower(Core::Configuration(self::PathRootParameterName, self::DefaultPathRoot));

		// If no execution path was specified or the path is not an api path we do nothing.

		self::CheckRequest();
		self::ParseRequest();
		self::ReadData();
		self::Call(self::$endpointName, self::$actionName, self::$uniqueID, self::$submoduleName, self::$submoduleUniqueID);
	}

	/** Asserts a condition and stops if condition is not met.
	 *
	 * If the condition is not met, the script execution is stopped and a custom status code is sent to the client.
	 *
	 * @param bool $assertCondition the condition to be asserted.
	 * @param int $statusCode the status code to be sent if the condition is not met.
	 *
	 * @see Respond()
	 */

	public static function Assert(bool $assertCondition, int $statusCode = HTTP::InternalServerError)
	{
		if (!$assertCondition) {
			self::Respond($statusCode);
		}
	}

	/** Validates data.
	 *
	 * If the data is not an array or any of its values are null, the script execution
	 * is stopped and HTTP::UnprocessableEntity status code is sent to the client.
	 *
	 * @param array $someData the data that must be checked.
	 *
	 * @see Respond()
	 */

	public static function CheckData(array $someData)
	{
		foreach ($someData as $currentValue) {
			if ($currentValue === null) {
				self::Respond(HTTP::UnprocessableEntity);
			} else if (is_array($currentValue)) {
				self::CheckData($currentValue);
			}
		}
	}

	/** Returns all request parameters and values.
	 * @see GetParameter()
	 */
	public static function GetAllParameters()
	{
		return self::$requestParameters;
	}

	/** Returns a request parameter value.
	 *
	 * Optionally, a validation expression can be used to validate the value.
	 * If a value from the request data is needed, please use GetValue().
	 *
	 * @param string $parameterName the parameter name from where the value must be returned.
	 * @param string $validationExpression (optional) a validation expression (regex) that must be used to validate the value.
	 * @return mixed the parameter value or null if it's not set or is not valid (does not match the validation expression).
	 *
	 * @see GetData(), GetValue()
	 */

	public static function GetParameter(string $parameterName, string $validationExpression = null)
	{
		$parameterValue = self::$requestParameters[$parameterName] ?? null;

		if (($validationExpression) && (preg_match($validationExpression, $parameterValue) != 1)) {
			$parameterValue = null;
		}

		return $parameterValue;
	}

	/** Returns the client sent request data.
	 *
	 * If a value from the request parameters is needed, please use GetParameter().
	 *
	 * @return array the JSON decoded request data.
	 *
	 * @see GetParameter()
	 */

	public static function GetData()
	{
		return self::$requestData;
	}

	/** Returns a request value from client sent request data.
	 *
	 * Optionally, a validation expression can be used to validate the value.
	 * If a value from the request parameters is needed, please use GetParameter().
	 *
	 * @param string $valueName the name of the value that must be returned.
	 * @param string $validationExpression (optional) a validation expression (regex) that must be used to validate the value.
	 * @return mixed the requested value or null if it's not set or is not valid (does not match the validation expression).
	 *
	 * @see GetData(), GetParameter()
	 */

	public static function GetValue(string $valueName, string $validationExpression = null)
	{
		$valueData = self::$requestData[$valueName] ?? null;

		if (($validationExpression) && (preg_match($validationExpression, $valueData) != 1)) {
			$valueData = null;
		}

		return $valueData;
	}

	/** Returns the request authentication token (if any).
	 *
	 * @return string the request authentication token or null if one was no specified.
	 */

	public static function GetAuthToken()
	{
		return self::$authToken;
	}

	/** Sends a response to the client.
	 *
	 * Sends a JSON response to the client and stops the script execution.
	 *
	 * @param int $httpStatus the HTTP status code to be sent.
	 * @param array $responseData (optional) some data to be sent with the response.
	 *
	 * @see CheckData()
	 */

	public static function Respond(int $httpStatus, ?array $responseData = null)
	{
		Core::CleanOutput();
		HTTP::Status($httpStatus);
		HTTP::DynamicHeaders('application/json', '', 0, array('Accept' => self::ValidContentType));

		if (is_array($responseData)) {
			echo json_encode($responseData, JSON_FORCE_OBJECT);
		}

		exit($httpStatus);
	}

	public static function URL(string $endpointName, ?string $uniqueID = null, ?string $submoduleName = null, ?string $submoduleUniqueID = null, ?string $apiVersion = null)
	{
		$apiVersion = $apiVersion ?? self::$apiVersion;
		$endpointUrl = Core::RootURL() . '/?api=' . self::DefaultPathRoot . $apiVersion . '/' . strtolower($endpointName);

		if (!empty($uniqueID)) {
			$endpointUrl .= '/' . $uniqueID;
		}

		if (!empty($submoduleName)) {
			$endpointUrl .= '/' . strtolower($submoduleName);
		}

		if (!empty($submoduleUniqueID)) {
			$endpointUrl .= '/' . $submoduleUniqueID;
		}

		return $endpointUrl;
	}

	/** Loads and calls an API endpoint.
	 *
	 * API endpoints are user objects (see Core::UserObject()) that are loaded from the 'APIv<currentAPIVersion>' directory and have the 'API' suffix.
	 * If the endpoint or the action is not found, the execution is halted with the HTTP::NotFound (404) status code.
	 * If the user object cannot be loaded, the execution is halted with the HTTP::InternalServerError (500) status code.
	 * If the call returns a status different from HTTP::OK (200), the script execution is halted with the returned status code.
	 *
	 * Eg.: if the endpoint 'Cart' and action 'Push' for version API 2 are called, the endpoint user object will be loaded from 'APIv2/Cart.API.php';
	 * It must have a fully qualifed class name equals to 'atREST\User\APIv2\Cart' and a method called 'Push' with the signature
	 * Push(array $requestData, $uniqueID = null) int, where the return value must be any of the valid HTTP status codes (HTTP::*).
	 *
	 * @see Core::UserObject()
	 */

	public static function Call(string $endpointName, string $actionName, ?string $uniqueID = null, ?string $submoduleName = null, ?string $submoduleUniqueID = null, ?string $apiVersion = null)
	{
		if (empty($endpointName) || empty($actionName)) {
			Core::Halt(HTTP::NotFound, self::Tag, 'Empty endpoint name or action (name: "' . $endpointName . '", action: "' . $actionName . '").');
		}

		if (!isset(self::$endpointObjects[$endpointName])) {
			$userObjectPrefix = self::DirectoryPathPrefix . ($apiVersion ?? self::$apiVersion) . '/';
			self::$endpointObjects[$endpointName] = Core::UserObject($userObjectPrefix . $endpointName, self::UserObjectSuffix);

			if (!self::$endpointObjects[$endpointName]) {
				Core::Halt(HTTP::NotFound);
			}
		}

		if (!empty($submoduleName)) {
			$actionName .= implode('', array_map('ucfirst', explode('-', $submoduleName)));
		}

		if (!is_callable(array(self::$endpointObjects[$endpointName], $actionName))) {
			Core::Halt(HTTP::NotFound, self::Tag, 'Action "' . $endpointName . '::' . $actionName . '" not found.');
		}

		$callReturn = empty($submoduleName) ?
			self::$endpointObjects[$endpointName]->$actionName(self::$requestData, $uniqueID) :
			self::$endpointObjects[$endpointName]->$actionName(self::$requestData, $uniqueID, $submoduleUniqueID);

		if ($callReturn != HTTP::OK) {
			Core::Halt($callReturn ? $callReturn : HTTP::InternalServerError);
		}
	}

	// Private Methods

	private static function CheckRequest()
	{
		self::$requestPath = $_GET['api'] ?? null;

		if (!self::$requestPath) {
			Core::Halt(HTTP::NotFound);
		}

		self::$requestInformation = HTTP::RequestInformation();
		self::$requestMethod = self::$requestInformation['Method-Override'] ?? self::$requestInformation['Method'];

		// Check if the request path is an API path.

		if (strtolower(substr(self::$requestPath, 0, strlen(self::$pathRoot))) != self::$pathRoot) {
			Core::Halt(HTTP::NotFound);
		}
	}

	private static function ParseRequest()
	{
		static $httpMethodsTable = array(
			'GET'		=> 'Pull',
			'POST'		=> 'Push',
			'PUT'		=> 'Upload',
			'PATCH'		=> 'Update',
			'DELETE'	=> 'Delete',
		);

		// Extract and check the request method.

		if (!self::$actionName = $httpMethodsTable[self::$requestMethod] ?? null) {
			Core::Halt(HTTP::MethodNotAllowed, self::Tag, 'Invalid method: ' . self::$requestMethod);
		}

		$pathRootLength = strlen(self::$pathRoot);

		// Extract the API version number.

		if (($versionEnd = strpos(self::$requestPath, '/', $pathRootLength)) === false) {
			$versionEnd = strlen(self::$requestPath);
		}

		self::$apiVersion = intval(substr(self::$requestPath, $pathRootLength, $versionEnd - $pathRootLength));

		if (self::$apiVersion <= 0) {
			Core::Halt(HTTP::NotFound, self::Tag, 'Invalid API version: ' . self::$apiVersion);
		}

		// Extract the root and parse the request path.
		// Path specification: /resource/resource_id/subresource/subresource_id?parameters

		self::$requestPath = substr(self::$requestPath, $versionEnd + 1);
		self::$requestParameters = array();

		$parametersStart = strpos(self::$requestPath, '?');

		if ($parametersStart > 0) {
			$parametersString = substr(self::$requestPath, $parametersStart + 1);
			self::$requestPath = substr(self::$requestPath, 0, $parametersStart);

			if (!empty($parametersString)) {
				foreach (explode('&', $parametersString) as $currentParameter) {
					$currentParameter = explode('=', $currentParameter);
					self::$requestParameters[urldecode($currentParameter[0])] = urldecode($currentParameter[1] ?? '');
				}
			}
		}

		// Read additional URL parameters

		foreach ($_GET as $parameterName => $parameterValue) {
			if ($parameterName == 'api') {
				continue;
			}

			$parameterName = implode('', array_map('ucfirst', explode('-', $parameterName ?? '')));
			self::$requestParameters[urldecode($parameterName)] = urldecode($parameterValue);
		}

		self::$requestPath = explode('/', self::$requestPath);

		self::$endpointName = implode('', array_map('ucfirst', explode('-', self::$requestPath[0] ?? '')));
		self::$uniqueID = self::$requestPath[1] ?? null;
		self::$submoduleName = self::$requestPath[2] ?? null;
		self::$submoduleUniqueID = self::$requestPath[3] ?? null;
		self::$authToken = self::$requestInformation['X-Auth-Token'] ?? null;

		if (empty(self::$endpointName)) {
			Core::Halt(HTTP::NotFound, self::Tag, 'Empty endpoint name (action: "' . self::$actionName . '").');
		}
	}

	private static function ReadData()
	{
		self::$requestData = file_get_contents('php://input');

		if ((self::$requestMethod == 'POST') || (self::$requestMethod == 'PATCH')) {
			$contentType = self::$requestInformation['Content-Type'] ?? 'empty/header';

			if ($contentType != 'application/json') {
				Core::Halt(HTTP::BadRequest, self::Tag, 'Unsupported content type: ' . $contentType);
			}

			if (!empty(self::$requestData)) {
				if (!self::$requestData = json_decode(self::$requestData, true)) {
					Core::Halt(HTTP::BadRequest, self::Tag, 'Request data decode error.');
				}
			}
		}

		if (!is_array(self::$requestData)) {
			self::$requestData = array();
		}
	}

	// Private Members

	private static $apiVersion = 0;
	private static $requestPath = '';
	private static $pathRoot = '';
	private static $requestParameters = array();
	private static $requestInformation = array();
	private static $requestMethod = '';
	private static $requestData = null;
	private static $endpointObjects = array();
	private static $endpointName = '';
	private static $actionName = '';
	private static $uniqueID = '';
	private static $submoduleName = '';
	private static $submoduleUniqueID = '';
	private static $authToken = null;
}

/** atREST Basic API Endpoint Trait
 */

trait Endpoint
{
	use Module;

	// Public Methods

	public function Pull(array $requestData, ?string $uniqueID = null)
	{
		return HTTP::MethodNotAllowed;
	}

	public function Push(array $requestData, ?string $uniqueID = null)
	{
		return HTTP::MethodNotAllowed;
	}

	public function Update(array $requestData, ?string $uniqueID = null)
	{
		return HTTP::MethodNotAllowed;
	}

	public function Delete(array $requestData, ?string $uniqueID = null)
	{
		return HTTP::MethodNotAllowed;
	}

	public function Upload(array $requestData, ?string $uniqueID = null)
	{
		return HTTP::MethodNotAllowed;
	}
}
