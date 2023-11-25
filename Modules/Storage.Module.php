<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

// FIXME: add documentation comments for class methods.

/** Generic data storage module.
 */

class Storage
{
	// Constants

	const Tag = 'Storage';

	const DefaultStorageParameterName	= 'Storage.Default';
	const StoragePrefix                 = 'Storage.';
	const ConnectorParameterName        = 'Connector';

	// Public Methods

	public static function __load()
	{
		// Empty: this just makes sure a global object for this module is not created.
	}

	public static function Get(string $storageName = null, array $storageAttributes = array())
	{
		if (!self::$isInitialized) {
			self::$loadedStorages = array();
			self::$defaultStorageName = Core::Configuration(self::DefaultStorageParameterName, '');
			self::$isInitialized = true;
		}

		if (!$storageName) {
			$storageName = self::$defaultStorageName;

			if (!$storageName) {
				Core::Log(Core::Error, self::Tag, 'Empty storage name.');
				return null;
			}
		}

		// If there a is storage already loaded for the specified storage with the specified attributes we just return it.

		$storageHash = hash('sha256', $storageName . serialize($storageAttributes));

		if (isset(self::$loadedStorages[$storageHash])) {
			return self::$loadedStorages[$storageHash];
		}

		// Check if the storage exists, try to load and open it.

		$storageParameters = Core::ConfigurationGroup(self::StoragePrefix . $storageName);

		if (!$storageParameters) {
			Core::Log(Core::Error, self::Tag, 'Storage "' . $storageName . '" not found.');
			return null;
		}

		$storageConnector = $storageParameters[self::ConnectorParameterName] ?? '';

		if (!$connectorObject = Core::Module('Storage/' . $storageConnector)) {
			Core::Log(Core::Error, self::Tag, 'Connector class "' . $storageConnector . '" not found.');
			return null;
		}

		// Set every attribute parameter to their value.

		unset($storageParameters[self::ConnectorParameterName]);

		foreach ($storageAttributes as $attributeName => $attributeValue) {
			foreach ($storageParameters as &$currentParameter) {
				$currentParameter = str_replace('{' . $attributeName . '}', $attributeValue, $currentParameter);
			}
			unset($currentParameter);
		}

		if (!$connectorObject->Open($storageParameters)) {
			unset($connectorObject);
			return null;
		}

		return self::$loadedStorages[$storageHash] = $connectorObject;
	}

	public static function GenerateID(string $idSalt)
	{
		return hash('sha256', uniqid(microtime() . $idSalt, true));
	}

	// Private Members

	private static $isInitialized = false;
	private static $defaultStorageName = null;
	private static $loadedStorages = null;
}

abstract class StorageConnector
{
	use Module;

	// Public Methods

	abstract public function Open(array $connectionAttributes);
	abstract public function Close();
	abstract public function Pull(string $groupName, string $idPropertyName, $objectID, array $neededProperties);
	abstract public function Push(string $groupName, string $idPropertyName, array $objectProperties);
	abstract public function Update(string $groupName, string $idPropertyName, $objectID, array $changedProperties);
	abstract public function Delete(string $groupName, string $idPropertyName, $objectID);
	abstract public function DeleteMany(string $groupName, array $deleteFilter);
	abstract public function Count(string $groupName, array $countFilter = null);
	abstract public function Search(string $groupName, array $neededProperties, ?array $searchFilter = null, ?array $resultsOrder = null, ?int $resultsLimit = null, ?int $resultsOffset = null);
	abstract public function FindOne(string $groupName, array $neededProperties, ?array $searchFilter = null, ?array $resultsOrder = null);

	public function Group(string $groupName)
	{
		return Core::UserObject(StorageGroup::DirectoryPath . $groupName, StorageGroup::UserObjectSuffix, array($this));
	}
}
