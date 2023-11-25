<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\Module;

// FIXME: add documentation comments for class methods.

abstract class StorageGroup
{
    use Module;

    // Constants

    const GroupName            = '';
    const IDPropertyName    = 'ID';
    const PullProperties    = array();
    const DefaultOrder      = array('ID');

    const DirectoryPath        = 'StorageGroups/';
    const UserObjectSuffix    = 'StorageGroup';

    const HashRuleRegEx = '/[a-f0-9]{64}/i';

    const IntegerRule    = 1;
    const FloatRule        = 2;
    const TextRule        = 3;
    const BooleanRule    = 4;
    const DateRule        = 5;
    const TimeRule        = 6;
    const HashRule      = 7;
    const CustomRule    = 99;

    const RuleNotFound      = 1;
    const IsRequired        = 2;
    const IsNotWritable        = 3;
    const InvalidValue        = 4;
    const DuplicateFound    = 5;

    // Public Methods

    public static function __load()
    {
        // Empty: this just makes sure a global object for this module is not created.
    }

    public static function Get(StorageConnector $storageConnector, string $groupName)
    {
        return Core::UserObject(StorageGroup::DirectoryPath . $groupName, StorageGroup::UserObjectSuffix, array($storageConnector));
    }

    public function __construct(StorageConnector $storageConnection)
    {
        $this->storageConnection = $storageConnection;
        $this->idPropertyName = static::IDPropertyName;
        $this->groupName = static::GroupName;
        $this->pullProperties = static::PullProperties;

        $this->existingMethods['BeforePull'] = method_exists($this, 'BeforePull');
        $this->existingMethods['BeforePush'] = method_exists($this, 'BeforePush');
        $this->existingMethods['BeforeUpdate'] = method_exists($this, 'BeforeUpdate');
        $this->existingMethods['BeforeDelete'] = method_exists($this, 'BeforeDelete');
        $this->existingMethods['BeforeSearch'] = method_exists($this, 'BeforeSearch');
        $this->existingMethods['AfterPull'] = method_exists($this, 'AfterPull');
        $this->existingMethods['AfterPush'] = method_exists($this, 'AfterPush');
        $this->existingMethods['AfterUpdate'] = method_exists($this, 'AfterUpdate');
        $this->existingMethods['AfterDelete'] = method_exists($this, 'AfterDelete');
        $this->existingMethods['AfterSearch'] = method_exists($this, 'AfterSearch');
        $this->existingMethods['TransformPropertiesIn'] = method_exists($this, 'TransformPropertiesIn');
        $this->existingMethods['TransformPropertiesOut'] = method_exists($this, 'TransformPropertiesOut');

        $this->SetUp();
    }

    public function Pull($objectID, ?array $neededProperties = null)
    {
        /* protected function BeforePull(&$objectID, array &$neededProperties);
         * protected function AfterPull(array &$objectProperties);
         * protected function TransformPropertiesOut(array &$objectProperties);
         */

        if (!is_array($neededProperties)) {
            $neededProperties = $this->pullProperties;
        }

        if ($this->existingMethods['BeforePull'] && !$this->BeforePull($objectID, $neededProperties)) {
            return false;
        }

        if (($objectProperties = $this->storageConnection->Pull($this->groupName, $this->idPropertyName, $objectID, $neededProperties)) === false) {
            return false;
        }

        if ($this->existingMethods['AfterPull'] && !$this->AfterPull($objectProperties)) {
            return false;
        }

        if ($this->existingMethods['TransformPropertiesOut'] && !$this->TransformPropertiesOut($objectProperties)) {
            return false;
        }

        return $objectProperties;
    }

    public function Push(array $objectProperties)
    {
        /* protected function BeforePush(array &$objectProperties);
         * protected function AfterPush(array $objectProperties, &$objectID);
         * protected function TransformPropertiesIn(array &$objectProperties);
         */

        if ($this->existingMethods['TransformPropertiesIn'] && !$this->TransformPropertiesIn($objectProperties)) {
            return false;
        }

        if (!$this->ValidateProperties($objectProperties)) {
            return false;
        }

        if ($this->existingMethods['BeforePush'] && !$this->BeforePush($objectProperties)) {
            return false;
        }

        if (!$objectID = $this->storageConnection->Push($this->groupName, $this->idPropertyName, $objectProperties)) {
            return false;
        }

        if ($this->existingMethods['AfterPush'] && !$this->AfterPush($objectProperties, $objectID)) {
            return false;
        }

        return $objectID;
    }

    public function Update($objectID, array $changedProperties)
    {
        /* protected function BeforeUpdate(&$objectID, array &$objectProperties);
         * protected function AfterUpdate($objectID, array $objectProperties);
         * protected function TransformPropertiesIn(array &$objectProperties);
         */

        if ($this->existingMethods['TransformPropertiesIn'] && !$this->TransformPropertiesIn($changedProperties)) {
            return false;
        }

        if (!$this->ValidateProperties($changedProperties, $objectID)) {
            return false;
        }

        if ($this->existingMethods['BeforeUpdate'] && !$this->BeforeUpdate($objectID, $changedProperties)) {
            return false;
        }

        if (!$this->storageConnection->Update($this->groupName, $this->idPropertyName, $objectID, $changedProperties)) {
            return false;
        }

        return $this->existingMethods['AfterUpdate'] ? $this->AfterUpdate($objectID, $changedProperties) : true;
    }

    public function Delete($objectID)
    {
        /* protected function BeforeDelete(&$objectID);
         * protected function AfterDelete($objectID);
         */

        if ($this->existingMethods['BeforeDelete'] && !$this->BeforeDelete($objectID)) {
            return false;
        }

        if (!$this->storageConnection->Delete($this->groupName, $this->idPropertyName, $objectID)) {
            return false;
        }

        return $this->existingMethods['AfterDelete'] ? $this->AfterDelete($objectID) : true;
    }

    public function DeleteMany(array $deleteFilter)
    {
        return $this->storageConnection->DeleteMany($this->groupName, $deleteFilter);
    }

    public function Count(?array $countFilter = null)
    {
        return $this->storageConnection->Count($this->groupName, $countFilter);
    }

    public function Search(?array $neededProperties = null, ?array $searchFilter = null, ?array $resultsOrder = null, ?int $resultsLimit = null, ?int $resultsOffset = null)
    {
        /* protected function BeforeSearch(&$neededProperties, &$searchFilter, &$resultsOrder, &$resultsLimit, &$resultsOffset);
         * protected function AfterSearch(&$searchResults);
         * protected function TransformPropertiesOut(&$objectProperties);
         */

        if (!$neededProperties) {
            $neededProperties = $this->pullProperties;
        }

        if ($this->existingMethods['BeforeSearch'] && !$this->BeforeSearch($neededProperties, $searchFilter, $resultsOrder, $resultsLimit, $resultsOffset)) {
            return false;
        }

        if (!$resultsOrder) {
            $resultsOrder = static::DefaultOrder;
        }

        if (($searchResults = $this->storageConnection->Search($this->groupName, $neededProperties, $searchFilter, $resultsOrder, $resultsLimit, $resultsOffset)) === false) {
            return false;
        }

        if ($this->existingMethods['AfterSearch'] && !$this->AfterSearch($searchResults)) {
            return false;
        }

        if ($this->existingMethods['TransformPropertiesOut']) {
            $transformedResults = array();

            foreach ($searchResults as $currentResult) {
                if ($this->TransformPropertiesOut($currentResult)) {
                    $transformedResults[] = $currentResult;
                }
            };

            $searchResults = $transformedResults;
        }

        return $searchResults;
    }

    public function FindOne(?array $neededProperties = null, ?array $searchFilter = null, ?array $resultsOrder = null)
    {
        /* protected function BeforePull(&$objectID, array &$neededProperties);
         * protected function AfterPull(array &$objectProperties);
         * protected function TransformPropertiesOut(array &$objectProperties);
         */

        if (!is_array($neededProperties)) {
            $neededProperties = $this->pullProperties;
        }

        if ($this->existingMethods['BeforePull'] && !$this->BeforePull($objectID, $neededProperties)) {
            return false;
        }

        if (($objectProperties = $this->storageConnection->FindOne($this->groupName, $neededProperties, $searchFilter, $resultsOrder)) === false) {
            return false;
        }

        if ($this->existingMethods['AfterPull'] && !$this->AfterPull($objectProperties)) {
            return false;
        }

        if ($this->existingMethods['TransformPropertiesOut'] && !$this->TransformPropertiesOut($objectProperties)) {
            return false;
        }

        return $objectProperties;
    }

    public function GetValidationResults()
    {
        return $this->validationResults;
    }

    public function GetRules()
    {
        return $this->groupRules;
    }

    public function Check(array $objectProperties, string $propertyName, $checkValue)
    {
        // function Check...($objectProperties, $checkValue)

        $methodName = 'Check' . $propertyName;
        return method_exists($this, $methodName) ? $this->$methodName($objectProperties, $checkValue) : false;
    }

    // Protected Methods

    protected abstract function SetUp();

    protected function AddUnique($uniqueNames)
    {
        if (is_string($uniqueNames)) {
            $uniqueNames = array($uniqueNames);
        }

        if (!is_array($uniqueNames)) {
            return false;
        }

        foreach ($uniqueNames as $currentName) {
            if (!in_array($currentName, $this->possibleDuplicateProperties)) {
                $this->possibleDuplicateProperties[] = $currentName;
            }
        }

        $this->uniqueProperties[] = $uniqueNames;
    }

    protected function AddIntegerRule(string $propertyName, bool $isRequired, bool $isWritable, ?int $defaultValue = null, ?int $minValue = null, ?int $maxValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::IntegerRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'minValue' => $minValue,
            'maxValue' => $maxValue,
        );
    }

    protected function AddFloatRule(string $propertyName, bool $isRequired, bool $isWritable, ?float $defaultValue = null, ?float $minValue = null, ?float $maxValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::FloatRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'minValue' => $minValue,
            'maxValue' => $maxValue,
        );
    }

    protected function AddTextRule(string $propertyName, bool $isRequired, bool $isWritable, ?string $defaultValue = null, ?string $validationRegex = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::TextRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'validationRegex' => $validationRegex,
        );
    }

    protected function AddHashRule(string $propertyName, bool $isRequired, bool $isWritable)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::HashRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => null,
        );
    }

    protected function AddBooleanRule(string $propertyName, bool $isRequired, bool $isWritable, ?bool $defaultValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::BooleanRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
        );
    }

    protected function AddDateRule(string $propertyName, bool $isRequired, bool $isWritable, ?int $minDate = null, ?int $maxDate = null, ?int $defaultValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::DateRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
        );
    }

    protected function AddTimeRule(string $propertyName, bool $isRequired, bool $isWritable, ?int $minTime = null, ?int $maxTime = null, ?int $defaultValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::TimeRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'minTime' => $minTime,
            'maxTime' => $maxTime,
        );
    }

    protected function AddCustomRule(string $propertyName, bool $isRequired, bool $isWritable, $defaultValue = null)
    {
        $this->groupRules[$propertyName] = array(
            'ruleType' => self::CustomRule,
            'isRequired' => $isRequired,
            'isWritable' => $isWritable,
            'defaultValue' => $defaultValue,
            'validationMethodName' => 'Validate' . ucfirst($propertyName),
        );
    }

    // Private Methods

    private function ValidateProperties(array &$propertiesList, $objectID = null)
    {
        $this->validationResults = array();

        // Check for properties with no rules.

        foreach ($propertiesList as $propertyName => $propertyValue) {
            if (($propertyName != $this->idPropertyName) && !isset($this->groupRules[$propertyName])) {
                $this->validationResults[$propertyName] = self::RuleNotFound;
            }
        }

        // Validate all other properties that have rules.

        foreach ($this->groupRules as $propertyName => $propertyRule) {
            if ($propertyName == $this->idPropertyName) {
                $this->validationResults[$propertyName] = self::IsNotWritable;
                continue;
            }

            if ($objectID && !isset($propertiesList[$propertyName])) {
                continue;
            }

            $isRequired = $propertyRule['isRequired'];

            if ($isRequired && !isset($propertiesList[$propertyName])) {
                if ($propertyRule['defaultValue'] === null) {
                    $this->validationResults[$propertyName] = self::IsRequired;
                } else {
                    $propertiesList[$propertyName] = $propertyRule['defaultValue'];
                }

                continue;
            }

            $keyExists = array_key_exists($propertyName, $propertiesList);
            $propertyValue = $keyExists ? $propertiesList[$propertyName] : null;
            $isNullOrEmpty = ($propertyValue == '') || is_null($propertyValue);

            if (!$isRequired && $keyExists && $isNullOrEmpty) {
                $propertiesList[$propertyName] = $propertyRule['defaultValue'];
                continue;
            }

            if (($objectID != null) && !$propertyRule['isWritable'] && isset($propertiesList[$propertyName])) {
                $this->validationResults[$propertyName] = self::IsNotWritable;
                continue;
            }

            $valueOK = true;

            switch ($propertyRule['ruleType']) {
                case self::IntegerRule:
                    if (!is_numeric($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    $propertyValue = intval($propertyValue);

                    if (($propertyRule['minValue'] !== null) && ($propertyValue < $propertyRule['minValue'])) {
                        $valueOK = false;
                        break;
                    }

                    if (($propertyRule['maxValue'] !== null) && ($propertyValue > $propertyRule['maxValue'])) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::FloatRule:
                    if (!is_numeric($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    $propertyValue = floatval($propertyValue);

                    if (($propertyRule['minValue'] !== null) && ($propertyValue < $propertyRule['minValue'])) {
                        $valueOK = false;
                        break;
                    }

                    if (($propertyRule['maxValue'] !== null) && ($propertyValue > $propertyRule['maxValue'])) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::TextRule:
                    if (!is_string($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    if (($propertyRule['validationRegex'] !== null) && (preg_match($propertyRule['validationRegex'], $propertyValue) != 1)) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::HashRule:
                    if (!is_string($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    if (preg_match(self::HashRuleRegEx, $propertyValue) != 1) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::BooleanRule:
                    $valueOK = is_bool($propertyValue);
                    break;

                case self::DateRule:
                    if (!is_numeric($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    $propertyValue = intval($propertyValue);

                    if (($propertyRule['minDate'] !== null) && ($propertyValue < $propertyRule['minDate'])) {
                        $valueOK = false;
                        break;
                    }

                    if (($propertyRule['maxDate'] !== null) && ($propertyValue < $propertyRule['maxDate'])) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::TimeRule:
                    if (!is_numeric($propertyValue)) {
                        $valueOK = false;
                        break;
                    }

                    $propertyValue = intval($propertyValue);

                    if (($propertyRule['minTime'] !== null) && ($propertyValue < $propertyRule['minTime'])) {
                        $valueOK = false;
                        break;
                    }

                    if (($propertyRule['maxTime'] !== null) && ($propertyValue < $propertyRule['maxTime'])) {
                        $valueOK = false;
                        break;
                    }

                    break;

                case self::CustomRule:
                    $validationMethodName = $propertyRule['validationMethodName'];
                    $valueOK = $this->$validationMethodName($propertyValue, $propertiesList);
                    break;
            }

            if (!$valueOK) {
                $this->validationResults[$propertyName] = self::InvalidValue;
            }
        }

        if (count($this->validationResults) > 0) {
            return false;
        }

        if (count($this->uniqueProperties) > 0) {
            $this->FindDuplicates($propertiesList, $objectID);
        }

        return count($this->validationResults) == 0;
    }

    private function FindDuplicates(array $propertiesList, $objectID = null)
    {
        $searchFilter = array();

        foreach ($this->uniqueProperties as $uniqueIndex => $currentNames) {
            $currentFilter = array();

            foreach ($currentNames as $currentName) {
                if (!isset($propertiesList[$currentName])) {
                    $currentFilter = false;
                    break;
                }

                $currentFilter[$currentName] = $propertiesList[$currentName];
            }

            if ($currentFilter) {
                $searchFilter['|Unique' . $uniqueIndex] = $currentFilter;
            }
        }

        if ($objectID) {
            $searchFilter['&' . static::IDPropertyName . '!'] = $objectID;
        }

        if (!$duplicateRecord = $this->Search($this->possibleDuplicateProperties, $searchFilter, null, 1)) {
            return;
        }

        if (!$duplicateRecord[0]) {
            return;
        }

        foreach ($this->possibleDuplicateProperties as $currentPropertyName) {
            if (!isset($propertiesList[$currentPropertyName])) {
                continue;
            }

            if ($propertiesList[$currentPropertyName] == $duplicateRecord[0][$currentPropertyName]) {
                $this->validationResults[$currentPropertyName] = self::DuplicateFound;
            }
        }
    }

    // Private Members

    private $storageConnection = null;
    private $groupName = '';
    private $idPropertyName = '';
    private $pullProperties = array();
    private $groupRules = array();
    private $validationResults = array();
    private $uniqueProperties = array();
    private $possibleDuplicateProperties = array();
    private $existingMethods = array();
}
