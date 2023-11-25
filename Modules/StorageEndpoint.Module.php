<?php

namespace atREST\Modules;

use atREST\API;
use atREST\HTTP;
use atREST\Endpoint;

// FIXME: add documentation comments.

class StorageEndpoint
{
    use Endpoint;

    const StorageName       = '';
    const GroupName         = '';
    const PullProperties    = null;
    const PushProperties    = null;
    const UpdateProperties  = null;
    const SearchProperties  = null;

    const CanPull        = true;
    const CanPush        = true;
    const CanUpdate     = true;
    const CanDelete     = true;
    const CountTotals   = true;

    public function __construct()
    {
        if (static::GroupName != '') {
            if (!$this->groupStorage = Storage::Get(static::StorageName)) {
                API::Respond(HTTP::InternalServerError);
            }

            $this->storageGroup = StorageGroup::Get($this->groupStorage, static::GroupName);

            if (!$this->storageGroup) {
                API::Respond(HTTP::InternalServerError);
            }

            $this->pullProperties = is_array(static::PullProperties) ? static::PullProperties : $this->storageGroup::PullProperties;
            $this->pushProperties = is_array(static::PushProperties) ? static::PushProperties : array();
            $this->updateProperties = is_array(static::UpdateProperties) ? static::UpdateProperties : array();
        }

        $this->existingMethods['BeforePull'] = method_exists($this, 'BeforePull');
        $this->existingMethods['BeforePush'] = method_exists($this, 'BeforePush');
        $this->existingMethods['BeforeUpdate'] = method_exists($this, 'BeforeUpdate');
        $this->existingMethods['BeforeDelete'] = method_exists($this, 'BeforeDelete');
        $this->existingMethods['AfterPull'] = method_exists($this, 'AfterPull');
        $this->existingMethods['AfterPush'] = method_exists($this, 'AfterPush');
        $this->existingMethods['AfterUpdate'] = method_exists($this, 'AfterUpdate');
        $this->existingMethods['AfterDelete'] = method_exists($this, 'AfterDelete');
    }

    public function Pull(array $requestData, $uniqueID = null)
    {
        /* protected function BeforePull(array &$requestData, $uniqueID, &$allowedProperties);
         * protected function AfterPull(array $requestData, $uniqueID, &$pulledData);
         */

        if (!static::CanPull) {
            return HTTP::MethodNotAllowed;
        }

        $allowedProperties = $this->pullProperties;

        if ($this->existingMethods['BeforePull']) {
            if (($beforePull = $this->BeforePull($requestData, $uniqueID, $allowedProperties)) !== HTTP::OK) {
                return $beforePull;
            }
        }

        $searchFilter = array();

        if (!empty($uniqueID)) {
            $pulledData = $this->storageGroup->Pull($uniqueID, $allowedProperties);
        } else {
            $resultsOrder = null;
            $resultsLimit = null;
            $resultsOffset = null;

            foreach (API::GetAllParameters() as $parameterName => $parameterValue) {
                switch ($parameterName) {
                    case 'OrderBy':
                        $orderValue = explode(',', $parameterValue);
                        $resultsOrder = array();

                        foreach ($orderValue as $currentValue) {
                            if (strpos($currentValue, ':') !== false) {
                                $valueData = explode(':', $currentValue);
                                $resultsOrder[$valueData[0]] = intval($valueData[1]) ?? 1;
                            } else {
                                $resultsOrder[$currentValue] = 1;
                            }
                        }

                        break;

                    case 'OffsetBy':
                        $resultsOffset = intval($parameterValue);
                        break;

                    case 'LimitBy':
                        $resultsLimit = intval($parameterValue);
                        break;

                    case 'Search':
                        $searchProperties = static::SearchProperties ?? array();

                        if (count($searchProperties) > 0) {
                            $autoSearchFilter = array();

                            foreach ($searchProperties as $fieldName) {
                                $autoSearchFilter['|' . $fieldName . '*'] = $parameterValue;
                            }

                            $searchFilter['AutoSearch'] = $autoSearchFilter;
                        }

                        break;

                    default:
                        $searchFilter[$parameterName] = $parameterValue;
                        break;
                }
            }

            $pulledData = $this->storageGroup->Search($allowedProperties, $searchFilter, $resultsOrder, $resultsLimit, $resultsOffset);
        }

        if (!is_array($pulledData)) {
            return empty($uniqueID) ? HTTP::InternalServerError : HTTP::NotFound;
        }

        if (empty($uniqueID) && static::CountTotals) {
            $pulledData = array(
                'Total' => $this->storageGroup->Count($searchFilter),
                'Items' => $pulledData,
            );
        }

        if ($this->existingMethods['AfterPull']) {
            if (($afterPull = $this->AfterPull($requestData, $uniqueID, $pulledData)) !== HTTP::OK) {
                return $afterPull;
            }
        }

        API::Respond(HTTP::OK, $pulledData);
    }

    public function Push(array $requestData, $uniqueID = null)
    {
        /* protected function BeforePush(array &$requestData, &$allowedProperties);
         * protected function AfterPush(array $requestData, &$newRecordID);
         */

        if (!static::CanPush || !empty($uniqueID)) {
            return HTTP::MethodNotAllowed;
        }

        $allowedProperties = $this->pushProperties;

        if ($this->existingMethods['BeforePush']) {
            if (($beforePush = $this->BeforePush($requestData, $allowedProperties)) !== HTTP::OK) {
                return $beforePush;
            }
        }

        $this->CheckAllowedProperties($requestData, $allowedProperties);

        if (!$newRecordID = $this->storageGroup->Push($requestData)) {
            API::Respond(HTTP::UnprocessableEntity, $this->storageGroup->GetValidationResults());
        }

        if ($this->existingMethods['AfterPush']) {
            if (($afterPush = $this->AfterPush($requestData, $newRecordID)) !== HTTP::OK) {
                return $afterPush;
            }
        }

        API::Respond(HTTP::OK, array('ID' => $newRecordID));
    }

    public function Update(array $requestData, $uniqueID = null)
    {
        /* protected function BeforeUpdate(&$requestData, $uniqueID, &$allowedProperties);
         * protected function AfterUpdate($requestData, $uniqueID);
         */

        if (!static::CanUpdate || empty($uniqueID)) {
            return HTTP::MethodNotAllowed;
        }

        $allowedProperties = $this->updateProperties;

        if ($this->existingMethods['BeforeUpdate']) {
            if (($beforeUpdate = $this->BeforeUpdate($requestData, $uniqueID, $allowedProperties)) !== HTTP::OK) {
                return $beforeUpdate;
            }
        }

        $this->CheckAllowedProperties($requestData, $allowedProperties);

        if (!$this->storageGroup->Update($uniqueID, $requestData)) {
            API::Respond(HTTP::UnprocessableEntity, $this->storageGroup->GetValidationResults());
        }

        if ($this->existingMethods['AfterUpdate']) {
            if (($afterUpdate = $this->AfterUpdate($requestData, $uniqueID)) !== HTTP::OK) {
                return $afterUpdate;
            }
        }

        return HTTP::OK;
    }

    public function Delete(array $requestData, $uniqueID = null)
    {
        /* protected function BeforeDelete(&$requestData, $uniqueID);
         * protected function AfterDelete($requestData, $uniqueID);
         */

        if (!static::CanDelete || empty($uniqueID)) {
            return HTTP::MethodNotAllowed;
        }

        if ($this->existingMethods['BeforeDelete']) {
            if (($beforeDelete = $this->BeforeDelete($requestData, $uniqueID)) !== HTTP::OK) {
                return $beforeDelete;
            }
        }

        if (!$this->storageGroup->Delete($uniqueID)) {
            return HTTP::InternalServerError;
        }

        if ($this->existingMethods['AfterDelete']) {
            if (($afterDelete = $this->AfterDelete($requestData, $uniqueID)) !== HTTP::OK) {
                return $afterDelete;
            }
        }

        return HTTP::OK;
    }

    public function GetGroup(string $groupName)
    {
        return StorageGroup::Get($this->groupStorage, $groupName);
    }

    // Protected Methods

    protected function CheckAllowedProperties(array $requestData, ?array $allowedProperties)
    {
        if (empty($allowedProperties)) {
            return;
        }

        $notAllowed = array();

        foreach ($requestData as $dataKey => $dataValue) {
            if (!in_array($dataKey, $allowedProperties)) {
                $notAllowed[] = $dataKey;
            }
        }

        if (!empty($notAllowed)) {
            API::Respond(HTTP::Forbidden, $notAllowed);
        }
    }

    // Protected Members

    protected $storageGroup = null;
    protected $groupStorage = null;

    // Private Members

    private $pushProperties = null;
    private $pullProperties = null;
    private $updateProperties = null;
    private $existingMethods = array();
}
