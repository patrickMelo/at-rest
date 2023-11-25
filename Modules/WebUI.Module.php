<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\HTTP;
use atREST\Module;

class WebUI
{
	use Module;

	const Tag = 'WebUI';

	const Assets	= 1;
	const Scripts	= 2;
	const Styles	= 3;
	const Templates = 4;

	const AssetsDirectoryPath		= 'WebUI/Assets/';
	const ImagesDirectoryPath		= 'WebUI/Images/';
	const ScriptsDirectoryPath		= 'WebUI/Scripts/';
	const StylesDirectoryPath		= 'WebUI/Styles/';
	const TemplatesDirectoryPath    = 'WebUI/Templates/';

	const TemplateStartRegEx		= '/<!--[\s]*Template:[\s]*([^\s]+)[\s]*-->/';
	const TemplateStartReplacement	= '<script type="x-webui/template" data-id="\1">';
	const TemplateEndRegEx			= '/<!--[\s]*End[\s]*-->/';
	const TemplateEndReplacement	= '</script>';
	const VarGroupRegEx				= '/@vars ([\w]+)[\s]*{([^}]+)}/';
	const VarRegEx					= '/([\w]+[\s]*):[\s]*([^;]+);/m';

	// Public Methods

	public static function __load()
	{
		// If no query string was specified we hijack the API request and deploy the WebUI.

		if (count($_GET) > 0) {
			return;
		}

		$rootDirectory = Core::DirectoryPath(Core::RootDirectory);

		self::$rootDirectoryLength = strlen($rootDirectory);

		self::$directoryPaths = array();
		self::$directoryPaths[self::Assets] = $rootDirectory . self::AssetsDirectoryPath;
		self::$directoryPaths[self::Scripts] = $rootDirectory . self::ScriptsDirectoryPath;
		self::$directoryPaths[self::Styles] = $rootDirectory . self::StylesDirectoryPath;
		self::$directoryPaths[self::Templates] = $rootDirectory . self::TemplatesDirectoryPath;

		self::$fileSystem = Core::Module('FileSystem');

		// Deploy the application code.

		// TODO: replace the assets tags by their paths.

		$scriptsCode = self::GenerateScriptsCode();
		$stylesCode = self::GenerateStylesCode();
		$fullTemplate = self::GenerateFullTemplate();

		$fullTemplate = str_replace('{App.Scripts}', $scriptsCode, $fullTemplate);
		$fullTemplate = str_replace('{App.Styles}', $stylesCode, $fullTemplate);

		HTTP::DynamicHeaders('text/html');
		Core::CleanOutput();

		echo $fullTemplate;

		Core::Halt(HTTP::OK);
	}

	public static function ImageURL(string $imageName)
	{
		return Core::RootURL() . '/' . self::ImagesDirectoryPath . $imageName;
	}

	public static function URL(string $routerPath)
	{
		return Core::RootURL() . '/#' . $routerPath;
	}

	// Private Methods

	private static function GenerateScriptsCode()
	{
		$scriptFiles = self::$fileSystem->ListFiles(self::$directoryPaths[self::Scripts], 'js');
		natsort($scriptFiles);

		$scriptsCode = '';
		$timeStamp = time();

		foreach ($scriptFiles as $currentScript) {
			$scriptsCode .= '<script type="text/javascript" src="' . substr($currentScript, self::$rootDirectoryLength) . '?' . $timeStamp . '"></script>' . Core::NewLine;
		}

		return $scriptsCode;
	}

	private static function CompileStyle(string $sourceFilePath)
	{
		$fileName = basename($sourceFilePath);
		$fileInfo = stat($sourceFilePath);
		$cacheID = $fileInfo['mtime'];

		/*if (!self::$forceStylesRecompile && ($cachedFilePath = self::$fileSystem->IsCached($fileName, $cacheID))) {
            return $cachedFilePath;
        }*/

		if (($sourceFileContents = file_get_contents($sourceFilePath)) === false) {
			return false;
		}

		/* Extract the style var groups (if any). */

		$currentOffset = 0;

		if (preg_match_all(self::VarGroupRegEx, $sourceFileContents, $varGroups, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
			foreach ($varGroups as $currentGroup) {
				$groupStart = $currentGroup[0][1];
				$groupLength = strlen($currentGroup[0][0]);
				$groupName = $currentGroup[1][0];
				$varItems = $currentGroup[2][0];

				self::$varGroups[$groupName] = array();

				if (preg_match_all(self::VarRegEx, $varItems, $varMatches, PREG_SET_ORDER) > 0) {
					foreach ($varMatches as $currentVar) {
						self::$varGroups[$groupName][$currentVar[1]] = $currentVar[2];
					}
				}

				$sourceFileContents = substr($sourceFileContents, 0, $groupStart + $currentOffset) . substr($sourceFileContents, $groupStart + $groupLength + $currentOffset);
				$currentOffset -= $groupLength;
			}

			self::$forceStylesRecompile = true;
		}

		/* Replace the current var groups. */

		foreach (self::$varGroups as $groupName => $currentVars) {
			foreach ($currentVars as $varName => $varValue) {
				$sourceFileContents = str_replace($groupName . '_' . $varName, $varValue, $sourceFileContents);
			}
		}

		return self::$fileSystem->SaveToCache($sourceFileContents, $fileName, $cacheID);
	}

	private static function GenerateStylesCode()
	{
		self::$varGroups = array();
		self::$forceStylesRecompile = false;

		$styleFiles = self::$fileSystem->ListFiles(self::$directoryPaths[self::Styles], 'css');
		natsort($styleFiles);

		$stylesCode = '';

		foreach ($styleFiles as $currentStyle) {
			$stylesCode .= '<link rel="stylesheet" type="text/css" href="' . self::CompileStyle($currentStyle) . '">' . Core::NewLine;
		}

		return $stylesCode;
	}

	private static function GenerateFullTemplate()
	{
		$templateFiles = self::$fileSystem->ListFiles(self::$directoryPaths[self::Templates], 'html');
		natsort($templateFiles);

		$fullTemplate = '';

		foreach ($templateFiles as $currentTemplate) {
			$currentContents = file_get_contents($currentTemplate);
			$currentContents = preg_replace(self::TemplateStartRegEx, self::TemplateStartReplacement, $currentContents);
			$currentContents = preg_replace(self::TemplateEndRegEx, self::TemplateEndReplacement . Core::NewLine, $currentContents);
			$fullTemplate .= $currentContents;
		}

		return $fullTemplate;
	}

	// Private Members

	private static $fileSystem = null;
	private static $directoryPaths = array();
	private static $rootDirectoryLength = 0;
	private static $varGroups = array();
	private static $forceStylesRecompile = false;
}
