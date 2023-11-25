<?php

namespace atREST\Modules\Storage;

use atREST\Core;
use atREST\Modules\Storage;
use atREST\Modules\StorageConnector;

class SQL extends StorageConnector {

	// Constants

	const Tag	= 'SQL';

	const UsernameParameterName 	= 'Username';
	const PasswordParameterName 	= 'Password';
	const TypeAttributeName	    	= 'Type';
	const TablePrefixAttributeName	= 'TablePrefix';

	const Unknown	= 0;
	const SQLite	= 1;
	const MariaDB	= 2;

	// Public Methods

	public function Open(array $connectionAttributes) {
		if ($this->currentConnection) {
			$this->Close();
		}

		static $sqlTypes = array(
			'SQLite' 	=> self::SQLite,
			'MariaDB'	=> self::MariaDB,
		);

		static $dnsStrings = array(
			self::SQLite	=> 'sqlite:{DataDirectory}{File}.sqlite',
			self::MariaDB	=> 'mysql:host={Host};port={Port};dbname={Database};',
		);

		static $dnsOptions = array(
			self::SQLite	=> array(),
			self::MariaDB	=> array(
				\PDO::ATTR_PERSISTENT => false,
				\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
				\PDO::MYSQL_ATTR_COMPRESS => true,
			),
		);

		$sqlType = $sqlTypes[$connectionAttributes[self::TypeAttributeName] ?? ''] ?? self::Unknown;

		if ($sqlType == self::Unknown) {
			$this->LogError('Unknown SQL type: "'.$sqlType.'".');
			return false;
		}

		$connectionAttributes['DataDirectory'] = Core::DirectoryPath(Core::DataDirectory);

		foreach ($connectionAttributes as $paramName => $paramValue) {
			$varNames[] = '{'.$paramName.'}';
		}

		$dsnString = str_replace($varNames, array_values($connectionAttributes), $dnsStrings[$sqlType]);

		$userName = $connectionAttributes[self::UsernameParameterName] ?? '';
		$userPassword = $connectionAttributes[self::PasswordParameterName] ?? '';

		$this->LogDebug($dsnString);
		$this->currentConnection = new \PDO($dsnString, $userName, $userPassword, $dnsOptions[$sqlType]);
		$this->tablePrefix = $connectionAttributes[self::TablePrefixAttributeName] ?? '';

		return true;
	}

	public function Close() {
		if (!$this->currentConnection) {
			return;
		}

		$this->currentConnection = null;
	}

	public function Pull(string $groupName, string $idPropertyName, $objectID, array $neededProperties) {
		$queryString = 'SELECT '.implode(', ', $neededProperties).' FROM '.$this->tablePrefix.$groupName.' WHERE '.$idPropertyName.' = :__ID';
		return $this->Query($queryString, array('__ID' => $objectID), true);
	}

    public function Push(string $groupName, string $idPropertyName, array $objectProperties) {
		$objectProperties[$idPropertyName] = Storage::GenerateID($groupName);
        $fieldNames = array_keys($objectProperties);

        $queryString = 'INSERT INTO '.$this->tablePrefix.$groupName.' ('.implode(', ', $fieldNames).') VALUES (:'.implode(', :', $fieldNames).')';

		return $this->Execute($queryString, $objectProperties) ?
			$objectProperties[$idPropertyName] :
			false;
	}

	public function Update(string $groupName, string $idPropertyName, $objectID, array $changedProperties) {
		$updateFields = array();

		foreach ($changedProperties as $propertyName => $propertyValue) {
			$updateFields[] = $propertyName.' = :'.$propertyName;
		}

		$queryString = 'UPDATE '.$this->tablePrefix.$groupName.' SET '.implode(', ', $updateFields).' WHERE '.$idPropertyName.' = :__ID';
		return $this->Execute($queryString, array_merge(array('__ID' => $objectID), $changedProperties));
	}

	public function Delete(string $groupName, string $idPropertyName, $objectID) {
		$queryString = 'DELETE FROM '.$this->tablePrefix.$groupName.' WHERE '.$idPropertyName.' = :ID';
		return $this->Execute($queryString, array('ID' => $objectID));
	}

	public function DeleteMany(string $groupName, array $deleteFilter) {
		$queryString = 'DELETE FROM '.$this->tablePrefix.$groupName.' WHERE '.$this->CreateSQLFilter($deleteFilter);
		return $this->Execute($queryString, $deleteFilter);
	}

    public function Count(string $groupName,array $countFilter = null) {
		$queryString = 'SELECT COUNT(*) AS totalRecords FROM '.$this->tablePrefix.$groupName;

		if ($countFilter) {
			$queryString .= ' WHERE '.$this->CreateSQLFilter($countFilter);
		}

		if (!$queryResult = $this->Query($queryString, $countFilter, true)) {
			return false;
		}

		return intval($queryResult['totalRecords']);
	}

	public function Search(string $groupName, array $neededProperties, ?array $searchFilter = null, ?array $resultsOrder = null, ?int $resultsLimit = null, ?int $resultsOffset = null) {
		$queryString = 'SELECT '.implode(', ', $neededProperties).' FROM '.$this->tablePrefix.$groupName;

		if ($searchFilter) {
			$queryString .= ' WHERE '.$this->CreateSQLFilter($searchFilter);
		}

		if ($resultsOrder) {
			$queryString .= ' ORDER BY '.$this->CreateSQLOrder($resultsOrder);
		}

		if ($resultsLimit) {
			if ($resultsOffset) {
				$queryString .= ' LIMIT '.$resultsOffset.', '.$resultsLimit;
			} else {
				$queryString .= ' LIMIT '.$resultsLimit;
			}
		}

		return $this->Query($queryString, $searchFilter);
	}

	public function FindOne(string $groupName, array $neededProperties, ?array $searchFilter = null, ?array $resultsOrder = null) {
		if ($searchResult = $this->Search($groupName, $neededProperties, $searchFilter, $resultsOrder)) {
			return $searchResult[0] ?? false;
		}

		return false;
	}

	// Private Methods

	private function Query(string $queryString, array $queryData = null, bool $queryOne = false) {
		if (!$queryStatement = $this->Prepare($queryString, $queryData)) {
			return false;
		}

		if (!$queryStatement->execute()) {
			$this->LogError($queryStatement->errorInfo()[2]);
			$this->LogError($queryStatement->queryString);
			$this->LogError(json_encode($queryData, JSON_FORCE_OBJECT));
			unset($queryStatement);
			return false;
		}

		$allRecords = $queryStatement->fetchAll(\PDO::FETCH_ASSOC);
		unset($queryStatement);

		if ($queryOne) {
			$allRecords = $allRecords[0] ?? array();
		}

		return $allRecords;
	}

	private function Execute(string $queryString, array $queryData = null) {
		if (!$queryStatement = $this->Prepare($queryString, $queryData)) {
			return false;
		}

		if (!$queryOK = $queryStatement->execute()) {
			$this->LogError($queryStatement->errorInfo()[2]);
			$this->LogError($queryStatement->queryString);
			$this->LogError(json_encode($queryData, JSON_FORCE_OBJECT));
		}

		unset($queryStatement);
		return $queryOK;
	}

	private function Prepare(string $queryString, array $queryData = null) {
		if (!$this->currentConnection) {
			return false;
		}

		$queryStatement = $this->currentConnection->prepare($queryString);

		if (!$queryStatement) {
			$this->LogError($this->currentConnection->errorInfo()[ 2 ]);
			$this->LogError($queryString);
			$this->LogError(json_encode($queryData, JSON_FORCE_OBJECT));
			return false;
		}

		$this->LogDebug($queryString);
		$this->LogDebug(json_encode($queryData, JSON_FORCE_OBJECT));

		if (is_array($queryData)) {
			if (!$this->BindData($queryStatement, $queryData)) {
				unset($queryStatement);
				return false;
			}
		}

		return $queryStatement;
	}

	private function BindData(\PDOStatement $queryStatement, array $queryData) {
		foreach ($queryData as $parameterName => $parameterValue) {
			$bindOK = false;

			switch(gettype($parameterValue)) {
				case 'string':
				case 'double': {
					$bindOK = $queryStatement->bindValue(':'.$parameterName, $parameterValue, \PDO::PARAM_STR);
					break;
				}

				case 'boolean': {
					$bindOK = $queryStatement->bindValue(':'.$parameterName, intval($parameterValue), \PDO::PARAM_BOOL);
					break;
				}

				case 'integer': {
					$bindOK = $queryStatement->bindValue(':'.$parameterName, intval($parameterValue), \PDO::PARAM_INT);
					break;
				}

				case 'array':
				case 'object': {
					$bindOK = $queryStatement->bindValue(':'.$parameterName, json_encode($parameterValue, JSON_FORCE_OBJECT), \PDO::PARAM_STR);
					break;
				}

				case 'NULL': {
					$bindOK = $queryStatement->bindValue(':'.$parameterName, null, \PDO::PARAM_NULL);
					break;
				}
			}

			if (!$bindOK) {
				$this->LogError($queryStatement->queryString);
				$this->LogError($queryStatement->errorInfo()[2]);
				return false;
			}
		} // foreach

		return true;
	}

	private function CreateSQLFilter(array &$storageFilter, ?string $groupName = null) {
		$sqlFilter = '';
		$firstField = true;

		$transformedFilter = array();

		foreach ($storageFilter as $fieldName => $fieldValue) {
			$fieldGlue = ' AND ';
			$valueGlue = is_null($fieldValue) ? ' IS ' : ' = ';

			switch ($fieldName[0]) {
				case '|': {
					$fieldGlue = ' OR ';
					$fieldName = substr($fieldName, 1);
					break;
				}

				case '&': {
					$fieldName = substr($fieldName, 1);
					break;
				}
			}

			$lastCharIndex = strlen($fieldName) - 1;

			switch ($fieldName[$lastCharIndex]) {
				case '>':
				case '<': {
					$valueGlue = ' '.$fieldName[$lastCharIndex].' ';
					$fieldName = substr($fieldName, 0, -1);
					break;
				}

				case '!': {
					$valueGlue = is_null($fieldValue) ? ' IS NOT ' : ' <> ';
					$fieldName = substr($fieldName, 0, -1);
					break;
				}

				case '=': {
					switch ($fieldName[$lastCharIndex - 1]) {
						case '>':
						case '<': {
							$valueGlue = ' '.$fieldName[$lastCharIndex - 1].'= ';
							$fieldName = substr($fieldName, 0, -2);
							break;
						}

						default: {
							$valueGlue = is_null($fieldValue) ? ' IS ' : ' '.$fieldName[$lastCharIndex].' ';
							$fieldName = substr($fieldName, 0, -1);
							break;
						}
					}

					break;
				}

				case '*': {
					$valueGlue = ' LIKE ';
					$fieldValue = '%'.$fieldValue.'%';
					$fieldName = substr($fieldName, 0, -1);
					break;
				}
			}

			if ($firstField) {
				$fieldGlue = '';
				$firstField = false;
			}

			if (is_array($fieldValue)) {
				$sqlFilter .= $fieldGlue.$this->CreateSQLFilter($fieldValue, $fieldName.'_');
				foreach($fieldValue as $currentName => $currentValue) {
					$transformedFilter[$fieldName.'_'.$currentName] = $currentValue;
				}
			} else {
				$sqlFilter .= $fieldGlue.$fieldName.$valueGlue.':'.($groupName ?? '').$fieldName;
				$transformedFilter[$fieldName] = $fieldValue;
			}
		} unset($fieldValue);

		$storageFilter = $transformedFilter;
		return '('.$sqlFilter.')';
	}

    private function CreateSQLOrder(array $storageOrder) {
        $sqlOrder = array();
        $orderTable = array(' DESC', ' ASC');

        foreach ($storageOrder as $fieldName => $fieldOrder) {
            if (is_numeric($fieldName)) {
                $sqlOrder[] = $fieldOrder.' ASC';
            } else {
                $sqlOrder[] = $fieldName.($orderTable[intval($fieldOrder)] ?? ' ASC');
            }
        }

        return implode(', ', $sqlOrder);
    }

	// Private Members

	private $currentConnection  = null;
	private $tablePrefix		= '';
}