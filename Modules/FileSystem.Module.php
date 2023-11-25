<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

/** Module for Filesystem related features (eg.: cached files).
 */

class FileSystem
{
	use Module;

	// Constants

	const Tag					= 'FileSystem';
	const CacheDirectoryPath	= 'Cache/';

	// Constructors & Destructors

	public function __construct()
	{
		if (!self::$cacheDirectoryPath) {
			self::$cacheDirectoryPath = Core::DirectoryPath(Core::RootDirectory) . self::CacheDirectoryPath;
		}
	}

	// Public Methods

	/** Lists files recursively.
	 * @param $directoryPath string the directory path from where the files must be listed.
	 * @param $includedExtensions mixed (optional) a comma separated list or array of extensions that if
	 * 											   specified only files with the specified extensions are listed.
	 * @return array the list of files found.
	 */
	public function ListFiles(string $directoryPath, $includedExtensions = '')
	{
		if (is_string($includedExtensions)) {
			$includedExtensions = explode(',', $includedExtensions);
		}

		$filesList = array();
		$checkExtesion = count($includedExtensions) > 0;

		foreach (glob($directoryPath . '*', GLOB_NOSORT) as $currentNode) {
			if (is_dir($currentNode)) {
				$filesList = array_merge($filesList, $this->ListFiles($currentNode . '/', $includedExtensions));
				continue;
			}

			if ($checkExtesion) {
				$fileExtension = pathinfo($currentNode, PATHINFO_EXTENSION);

				if (!in_array($fileExtension, $includedExtensions)) {
					continue;
				}
			}

			$filesList[] = $currentNode;
		}

		return $filesList;
	}

	public function IsCached(string $fileName, string $cacheID)
	{
		$cacheFileName = $this->CacheFileName($fileName, $cacheID);
		return is_file(self::$cacheDirectoryPath . $cacheFileName) ? self::CacheDirectoryPath . $cacheFileName : false;
	}

	/** Saves a file to the cache directory and returns its path.
	 *
	 * The file contents are only saved if it's not cached already.
	 * If the file is already cached nothing is saved, it just returns the cached file path.
	 *
	 * @param $fileContents string the contents to be cached.
	 * @param $fileName string the file name to be used when saving to the cache.
	 * @return string the relative cached file path.
	 */
	public function SaveToCache(string $fileContents, string $fileName, string $cacheID)
	{
		if (!is_dir(self::$cacheDirectoryPath)) {
			mkdir(self::$cacheDirectoryPath, 0700, true);
		}

		$cacheFileName = $this->CacheFileName($fileName, $cacheID);
		$targetFilePath = self::$cacheDirectoryPath . $cacheFileName;

		//if (!is_file($targetFilePath)) {
		if (!file_put_contents($targetFilePath, $fileContents)) {
			$this->LogError('Could not save the file contents to "' . $targetFilePath . '".');
			return '';
		}
		//}

		return self::CacheDirectoryPath . $cacheFileName;
	}

	private function CacheFileName(string $fileName, string $cacheID)
	{
		$pathInfo = pathinfo($fileName);
		return $pathInfo['basename'] . '.' . $cacheID . '.' . $pathInfo['extension'];
	}

	private static $cacheDirectoryPath = '';
}
