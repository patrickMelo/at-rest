<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\HTTP;
use atREST\API;
use atREST\Endpoint;

// FIXME: add documentation comments.

class SecureEndpointWrapper
{
    use Endpoint;

    const Tag               = 'SecureEndpointWrapper';
    const BypassSecurity    = array();

    // Public Methods

    public static function __load()
    {
        // Empty
    }

    public function __construct()
    {
        $calledClass = get_called_class();
        $lastSlash = strrpos($calledClass, '\\');
        $namespaceOnly = substr($calledClass, 0, $lastSlash + 1);
        $classNameOnly = substr($calledClass, $lastSlash + 1);
        $insecureClassName = $namespaceOnly . 'Insecure' . $classNameOnly;

        if (!class_exists($insecureClassName)) {
            Core::Halt(HTTP::InternalServerError, static::Tag, 'Insecure endpoint class "' . $insecureClassName . '" not found.');
        }

        $this->insecureEndpoint = new $insecureClassName();
    }

    public function Pull(array $requestData, $uniqueID = null)
    {
        return self::Secure('Pull', array($requestData, $uniqueID));
    }

    public function Push(array $requestData, $uniqueID = null)
    {
        return $this->Secure('Push', array($requestData, $uniqueID));
    }

    public function Update(array $requestData, $uniqueID = null)
    {
        return $this->Secure('Update', array($requestData, $uniqueID));
    }

    public function Delete(array $requestData, $uniqueID = null)
    {
        return $this->Secure('Delete', array($requestData, $uniqueID));
    }

    public function Upload(array $requestData, $uniqueID = null)
    {
        return $this->Secure('Upload', array($requestData, $uniqueID));
    }

    public function __call(string $methodName, array $callArguments)
    {
        if (preg_match('/^(Pull|Push|Update|Delete|Upload)(.*)$/', $methodName, $nameMatches) != 1) {
            return HTTP::MethodNotAllowed;
        }

        if (!is_callable(array($this->insecureEndpoint, $methodName))) {
            Core::Halt(HTTP::NotFound, static::Tag, 'Submodule action "' . $methodName . '" not found.');
        }

        return $this->Secure($methodName, $callArguments);
    }

    public function Secure(string $methodName, array $callArguments)
    {
        if (in_array($methodName, static::BypassSecurity)) {
            return $this->insecureEndpoint->$methodName(...$callArguments);
        }

        if (!$authorizationModule = Core::Module('Authorization')) {
            Core::Halt(HTTP::InternalServerError, static::Tag, 'Authorization module not found.');
        }

        $this->apiToken = API::GetAuthToken();

        if (!$this->authorizedToken = $authorizationModule->Validate($this->apiToken)) {
            return HTTP::Unauthorized;
        }

        return $this->insecureEndpoint->$methodName(...$callArguments);
    }

    // Protected Members

    protected $apiToken;
    protected $authorizedToken;

    // Private Members

    private $insecureEndpoint = null;
}
