<?php

namespace atREST\Modules;

use atREST\Core;
use atREST\HTTP;

class ErrorLogger
{
    const ErrorTag      = 'Error';
    const ExceptionTag  = 'Exception';

    // Public Methods

    public static function __load()
    {
        set_error_handler(__CLASS__ . '::LogError', E_ALL);
        set_exception_handler(__CLASS__ . '::LogException');
    }

    public static function LogError(int $errorCode, string $errorMessage, string $filePath, int $fileLine, array $errorContext)
    {
        Core::Log(Core::Error, self::ErrorTag, $errorMessage);

        if (!empty($filePath)) {
            Core::Log(Core::Debug, self::ErrorTag, $filePath . ':' . $fileLine);
        }

        if (!empty($errorContext)) {
            if (!$encodedContext = json_encode($errorContext, JSON_FORCE_OBJECT)) {
                $encodedContext = serialize($errorContext);
            }

            Core::Log(Core::Debug, self::ErrorTag, $encodedContext);
        }

        return Core::MaxLogLevel() < Core::Debug;
    }

    public static function LogException($exceptionObject)
    {
        Core::Log(Core::Error, self::ExceptionTag, $exceptionObject->getMessage());
        Core::Log(Core::Error, self::ExceptionTag, $exceptionObject->getFile() . ':' . $exceptionObject->getLine());
        Core::Log(Core::Error, self::ExceptionTag, json_encode($exceptionObject->getTrace(), JSON_FORCE_OBJECT));

        if ($previousException = $exceptionObject->getPrevious()) {
            Self::LogException($previousException);
        }

        Core::Halt(HTTP::InternalServerError);
    }
}
