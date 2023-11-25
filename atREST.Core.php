<?php

namespace atREST;

/** atREST Core Class
 */

class Core
{
	// Constants

	const Version	= 000100; // Major, Minor, Fix
	const NewLine	= "\n";
	const Tag      	= 'Core';

	// Constants: Configuration Parameter Names

	const AllowCORSParameterName			= 'Core.AllowCORS';
	const LogFileNameFormatParameterName	= 'Core.Log.FileNameFormat';
	const MaxLogLevelParameterName	        = 'Core.Log.MaxLevel';
	const MemoryLimitParameterName			= 'Core.MemoryLimit';
	const ModeParameterName					= 'Core.Mode';
	const ModulesParameterName				= 'Core.Modules';

	// Constants: Configuration Parameter Values

	const DefaultMemoryLimit		= '32M';
	const DefaultLogFileNameFormat  = 'Y-m-d';
	const DefaultMaxLogLevel		= self::Error;

	// Constants: Log Level

	const Error			= 0;
	const Warning		= 1;
	const Information	= 2;
	const Debug			= 3;

	// Constants: Directory Indexes

	const RootDirectory				= 0;
	const DataDirectory				= 1;
	const LogsDirectory				= 2;
	const ModulesDirectory			= 3;
	const UserDirectory				= 4;
	const VendorDirectory			= 5;

	const RelativeDataDirectory		= 101;
	const RelativeLogsDirectory		= 102;
	const RelativeModulesDirectory	= 103;
	const RelativeUserDirectory		= 104;
	const RelativeVendorDirectory		= 104;

	// Constants: Directory Paths

	const DataDirectoryPath		= 'Data/';
	const LogsDirectoryPath		= 'Data/Logs/';
	const ModulesDirectoryPath	= 'Core/Modules/';
	const UserDirectoryPath		= 'User/';
	const VendorDirectoryPath	= 'Vendor/';

	// Public Methods: General

	/** Initializes (configures) the framework and loads the needed modules.
	 *
	 * In case of errors, an HTTP status 500 is sent to the client and the execution is halted.
	 * The framework run mode can be set using the 'Core.Mode' config parameter.
	 * The memory limit can be set using the 'Core.MemoryLimit' config parameter.
	 * The log file name format can be set using the 'Core.Log.FileNameFormat' config parameter.
	 * The maximum level for enabled log messages can be set using the 'Core.Log.MaxLevel' config parameter.
	 * CORS can be enabled/disabled using the 'Core.AllowCORS' config parameter.
	 * Required modules can be set to be loaded on the framework initialization using the 'Core.Module' config parameter.
	 *
	 * @param array $initialConfiguration the configuration parameters to be used on the current execution.
	 *
	 * @see Configuration(), Mode(), Module()
	 */

	public static function Initialize(array $initialConfiguration)
	{
		if (self::$currentConfiguration) {
			return;
		}

		self::$startTime = microtime(true);
		self::$currentConfiguration = self::NormalizeConfiguration($initialConfiguration);

		// Configuration

		self::$currentMode = self::Configuration(self::ModeParameterName, '');
		self::$maxLogLevel = self::Configuration(self::MaxLogLevelParameterName, self::DefaultMaxLogLevel);

		if (self::$maxLogLevel >= self::Debug) {
			ini_set('display_errors', 'On');
			error_reporting(E_ALL);
		} else {
			ini_set('display_errors', 'Off');
		}

		ini_set('memory_limit', self::Configuration(self::MemoryLimitParameterName, self::DefaultMemoryLimit));

		if (self::Configuration(self::AllowCORSParameterName, false)) {
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Request-Headers: X-Requested-With, Content-Type');
		}

		// Directories & URLs

		self::$directoriesPaths = array();
		self::$directoriesPaths[self::RootDirectory] = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)) . '/';
		self::$directoriesPaths[self::DataDirectory] = self::$directoriesPaths[self::RootDirectory] . self::DataDirectoryPath;
		self::$directoriesPaths[self::LogsDirectory] = self::$directoriesPaths[self::RootDirectory] . self::LogsDirectoryPath;
		self::$directoriesPaths[self::ModulesDirectory] = self::$directoriesPaths[self::RootDirectory] . self::ModulesDirectoryPath;
		self::$directoriesPaths[self::UserDirectory] = self::$directoriesPaths[self::RootDirectory] . self::UserDirectoryPath;
		self::$directoriesPaths[self::VendorDirectory] = self::$directoriesPaths[self::RootDirectory] . self::VendorDirectoryPath;
		self::$directoriesPaths[self::RelativeDataDirectory] = self::DataDirectoryPath;
		self::$directoriesPaths[self::RelativeLogsDirectory] = self::LogsDirectoryPath;
		self::$directoriesPaths[self::RelativeModulesDirectory] = self::ModulesDirectoryPath;
		self::$directoriesPaths[self::RelativeUserDirectory] = self::UserDirectoryPath;
		self::$directoriesPaths[self::RelativeVendorDirectory] = self::VendorDirectoryPath;

		$requestProtocol = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
		self::$rootURL = $requestProtocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

		// Log File

		$logFilePath = self::$directoriesPaths[self::LogsDirectory] . date(self::Configuration(self::LogFileNameFormatParameterName, self::DefaultLogFileNameFormat)) . '.log';

		if (!is_dir(self::$directoriesPaths[self::LogsDirectory])) {
			mkdir(self::$directoriesPaths[self::LogsDirectory], 0700, true);
		}

		self::$logFile = fopen($logFilePath, 'a');

		if (!self::$logFile) {
			self::Halt(HTTP::InternalServerError);
		}

		// Required Modules

		$modulesOK = true;
		self::$loadedModules = array();

		foreach (self::ConfigurationGroup(self::ModulesParameterName) as $currentModule) {
			if (!self::Module($currentModule)) {
				$modulesOK = false;
			}
		}

		if (!$modulesOK) {
			self::Halt(HTTP::InternalServerError);
		}
	}

	/** Halts the framework execution and sends an status code to the client. Optionally, logs an error message.
	 *
	 * @param int $httpStatusCode the status code to be sent to the client (use HTTP::* constants for the supported status codes).
	 * @param string $logTag (optional) the log tag to use when logging the error message.
	 * @param string $logMessage (optional) a error message to be logged before the execution stops.
	 *
	 * @see Log()
	 */

	public static function Halt(int $httpStatusCode, ?string $logTag = null, ?string $logMessage = null)
	{
		if (($logTag) && ($logMessage)) {
			self::Log(self::Error, $logTag, $logMessage);
		}

		if (headers_sent()) {
			exit($httpStatusCode);
		}

		self::CleanOutput();
		exit(HTTP::Status($httpStatusCode));
	}

	/** Cleans the currently buffered output (only when not in debug).
	 *
	 * @return string the existing buffered output before the cleaning.
	 */

	public static function CleanOutput()
	{
		$currentOutput = '';

		if ((ob_get_length() !== false) && (self::$maxLogLevel < self::Debug)) {
			$currentOutput = ob_get_contents();
			ob_end_clean();
		}

		return $currentOutput;
	}

	/** Returns the framework version string or the version string for a specified version number.
	 *
	 * @param int $versionNumber (optional) the version number for the equivalent string to be returned. If not specified the framework version number is used.
	 *
	 * @return string the version string of the specified version number.
	 */

	public static function VersionString(int $versionNumber = Core::Version)
	{
		return intdiv($versionNumber, 10000) . '.' . intdiv($versionNumber % 10000, 100) . '.' . ($versionNumber % 100);
	}

	/** Returns the current running mode of the framework.
	 *
	 * Can be set using the 'Core.Mode' config parameter.
	 *
	 * @return string the current running mode.
	 */

	public static function Mode()
	{
		return self::$currentMode;
	}

	/** Returns the current script execution time.
	 * @return float the current script exection time.
	 */

	public static function ExecutionTime()
	{
		return microtime(true) - self::$startTime;
	}

	// Public Methods: Paths & URLs

	/** Checks if a path is safe to be used.
	 *
	 * To be considered safe, the path must be inside the framework root directory and must not contain any backtracks (/../).
	 *
	 * @param string $nodePath the path (file or directory) to be checked.
	 *
	 * @return bool whether the path is safe or not.
	 */

	public static function CheckPath(string $nodePath)
	{
		if (strpos(str_replace('\\', '/', $nodePath), '../') !== false) {
			return false;
		}

		$pathBase = substr($nodePath, 0, strlen(self::$directoriesPaths[self::RootDirectory]));

		if ($pathBase != self::$directoriesPaths[self::RootDirectory]) {
			return false;
		}

		return true;
	}

	/** Returns a framework directory path.
	 *
	 * Valid indexes are: Core::RootDirectory, Core::DataDirectory, Core::LogsDirectory, Core::ModulesDirectory,
	 *                    Core::UserDirectory, Core::VendorDirectory, Core::RelativeDataDirectory, Core::RelativeLogsDirectory,
	 *                    Core::RelativeModulesDirectory, Core::RelativeUserDirectory and Core::RelativeVendorDirectory.
	 *
	 * Paths with indexes containing the "Relative" prefix are relative to the framework root, otherwise are absolute.
	 *
	 * @param int $directoryIndex the index of the directory.
	 *
	 * @return string the path (relative or absolute) of the requested directory (empty if a invalid index is specified).
	 */

	public static function DirectoryPath(int $directoryIndex)
	{
		return self::$directoriesPaths[$directoryIndex] ?? '';
	}

	/** Returns the current root URL for the framework (detected at runtime).
	 */

	public static function RootURL()
	{
		return self::$rootURL;
	}

	// Public Methods: Log & Configuration

	/** Logs a message to the log file.
	 *
	 * Valid message levels are: Core::Information, Core::Warning, Core::Error or Core::Debug.
	 *
	 * @param int $logLevel the message level.
	 * @param string $logTag the log message group tag.
	 * @param string $logMessage the actual message.
	 */

	public static function Log(int $logLevel, string $logTag, string $logMessage)
	{
		static $logLevelStrings = array(
			self::Error => '[E] ',
			self::Warning => '[W] ',
			self::Information => '[I] ',
			self::Debug => '[D] ',
		);

		if ($logLevel > self::$maxLogLevel) {
			return;
		}

		fwrite(self::$logFile, $logLevelStrings[$logLevel] . date('Y/m/d H:i:s |') . $logTag . '| ' . $logMessage . self::NewLine);
	}

	public static function MaxLogLevel()
	{
		return self::$maxLogLevel;
	}

	/** Returns a parameter value from the current configuration.
	 *
	 * @param string $parameterName the name of the parameter.
	 * @param mixed $defaultValue (optional) a default value to be returned if the parameter is not set.
	 *
	 * @return mixed the current value for the specified parameter or $defaultValue if the parameter is not set.
	 */

	public static function Configuration(string $parameterName, $defaultValue = null)
	{
		return
			self::$currentConfiguration[self::$currentMode . '.' . $parameterName] ??
			self::$currentConfiguration[$parameterName] ??
			$defaultValue;
	}

	// FIXME: include documentation comment for ConfigurationGroup.

	public static function ConfigurationGroup(string $rootName)
	{
		if (isset(self::$configurationGroupCache[$rootName])) {
			return self::$configurationGroupCache[$rootName];
		}

		self::$configurationGroupCache[$rootName] = array();
		$rootNameLength = strlen($rootName);
		$modeName = self::$currentMode . '.' . $rootName;
		$modeNameLength = strlen($modeName);

		foreach (self::$currentConfiguration as $parameterName => $parameterValue) {
			if (substr($parameterName, 0, $rootNameLength) == $rootName) {
				self::$configurationGroupCache[$rootName][substr($parameterName, $rootNameLength + 1)] = $parameterValue;
			} else if (substr($parameterName, 0, $modeNameLength) == $modeName) {
				self::$configurationGroupCache[$rootName][substr($parameterName, $modeNameLength + 1)] = $parameterValue;
			}
		}

		return self::$configurationGroupCache[$rootName];
	}

	// Public Methods: Modules & User Objects

	/** Safely includes a source file into the current execution.
	 *
	 * If the file path is not readable or insecure (outside the framework root directory) it does not get included.
	 * Any include warning or error gets logged.
	 * An optional "needed" classname can be specified: if the class is not found after the file inclusion it returns false.
	 * To load modules or user objects do no call this method directly, use Module() or UserObject() instead.
	 *
	 * @param string $filePath the path of the file to be included (it must be inside the framework root directory).
	 * @param string $neededClassName (optional) a class name to be checked for existence after the file inclusion.
	 *
	 * @return bool true whether the file is successfully included (and the optional class name is found).
	 *
	 * @see Module(), UserObject()
	 */

	public static function Include(string $filePath, string $neededClassName = '')
	{
		$filePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);

		if (!is_readable($filePath) || !self::CheckPath($filePath)) {
			self::Log(self::Error, self::Tag, 'The file "' . $filePath . '" cannot be read or has an insecure path.');
			return false;
		}

		if (!include_once($filePath)) {
			self::Log(self::Error, self::Tag, 'Could not include the file "' . $filePath . '".');
			return false;
		}

		if (($neededClassName != '') && (!class_exists($neededClassName))) {
			self::Log(self::Error, self::Tag, 'Class "' . $neededClassName . '" not found.');
			return false;
		}

		return true;
	}

	// FIXME: include documentation comment for IncludeVendor.

	public static function IncludeVendor($filePath)
	{
		return self::Include(self::$directoriesPaths[self::VendorDirectory] . $filePath);
	}

	/** Loads a framework module.
	 *
	 * Each module just gets loaded once and a public module object is created in the global modules space (if the
	 * module class has a static method called "__load" it get's called instead of creating the module object).
	 * All module files must reside inside the modules directory (<root>/Modules) and have a ".Module.php" suffix.
	 * A file can reside inside a subdirectory in the modules directory, but it's fully qualified class name must match the file path.
	 * Every module class must be part of the 'Bafo\Modules' namespace and may extend the Bafo\Module class.
	 *
	 * Eg.: a module file in <root>/Modules/Example/My.Module.php must have a class named with the fully
	 *      qualified name "Bafo\Modules\Example\My" and can be loaded using Core::Module('Example/My').
	 *
	 * If a user object must be loaded (part of the application code, not the framework), please use UserObject().
	 *
	 * @param $moduleName string the fully qualified name (without 'Bafo\Modules' prefix) of the module that must be loaded.
	 *
	 * @return object the loaded module object or null in case of errors.
	 *
	 * @see UserObject()
	 */

	public static function Module(string $moduleName, array $createParameters = null)
	{
		$className = 'atREST\\Modules\\' . str_replace('/', '\\', $moduleName);

		if (!class_exists($className)) {
			$moduleFilePath = self::$directoriesPaths[self::ModulesDirectory] . $moduleName . '.Module.php';

			if (!self::Include($moduleFilePath, $className)) {
				return null;
			}
		}

		if (is_callable($className . '::__load')) {
			call_user_func($className . '::__load');
		} else if (!isset(self::$loadedModules[$className])) {
			self::$loadedModules[$className] = new $className();
		}

		return isset(self::$loadedModules[$className]) ? self::$loadedModules[$className] : true;
	}

	/** Loads an user object.
	 *
	 * User objects are always private (in contrast to module objects, that are always public).
	 * All user object files must reside inside the user directory (<root>/User) and have no fixed suffix (but must have one).
	 * A file can reside inside a subdirectory in the user directory, but it's fully qualified class name must match the file path.
	 * Every user object class must be part of the 'atREST\User' namespace.
	 *
	 * Eg.: a user object file in <root>/User/Example/My.Suffix.php must have a class named with the fully
	 *      qualified name "atREST\User\Example\My" and can be loaded using Core::UserObject('Example/My', 'Suffix').
	 *
	 * If a framework module must be loaded (part of the framework code, not the application), please use Module().
	 *
	 * @param string $objectPath the object file path, excluding the <root>/User prefix and the file suffix.
	 * @param string $fileSuffix the user object file name suffix.
	 * @param array $createParameters (optional) an array of parameters to be passed to the user object constructor.
	 *
	 * @return object the loaded user object or null in case of errors.
	 *
	 * @see Module()
	 */

	public static function UserObject(string $objectPath, string $fileSuffix, ?array $createParameters = null)
	{
		$className = 'atREST\\User\\' . str_replace('/', '\\', $objectPath);

		if (!class_exists($className)) {
			$objectFilePath = self::$directoriesPaths[self::UserDirectory] . $objectPath . '.' . $fileSuffix . '.php';

			if (!self::Include($objectFilePath, $className)) {
				return null;
			}
		}

		return $createParameters ? new $className(...$createParameters) : new $className();
	}

	// Private Methods

	private static function NormalizeConfiguration(array $configurationParameters, string $currentPath = '')
	{
		$normalizedConfiguration = array();

		if ($currentPath != '') {
			$currentPath .= '.';
		}

		foreach ($configurationParameters as $parameterName => $parameterValue) {
			if (is_array($parameterValue)) {
				$normalizedConfiguration = array_merge($normalizedConfiguration, self::NormalizeConfiguration($parameterValue, $currentPath . $parameterName));
			} else {
				$normalizedConfiguration[$currentPath . $parameterName] = $parameterValue;
			}
		}

		return $normalizedConfiguration;
	}

	// Private Members

	private static $currentMode = '';
	private static $currentConfiguration = null;
	private static $maxLogLevel = -1;
	private static $logFile = null;
	private static $directoriesPaths = null;
	private static $rootURL = '';
	private static $loadedModules = null;
	private static $configurationGroupCache = array();
	private static $startTime = 0;
}

/** atREST Basic Module Trait
 */

trait Module
{
	// General

	public function GetInstance()
	{
		$className = get_called_class();
		return new $className();
	}

	// Log

	public function LogInformation(string $logMessage)
	{
		Core::Log(Core::Information, static::Tag, $logMessage);
	}

	public function LogWarning(string $logMessage)
	{
		Core::Log(Core::Warning, static::Tag, $logMessage);
	}

	public function LogError(string $logMessage)
	{
		Core::Log(Core::Error, static::Tag, $logMessage);
	}

	public function LogDebug(string $logMessage)
	{
		Core::Log(Core::Debug, static::Tag, $logMessage);
	}
}
