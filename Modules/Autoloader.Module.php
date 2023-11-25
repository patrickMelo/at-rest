<?php

namespace atREST\Modules;

use atREST\Core;

class Autoloader
{
    // Public Methods

    public static function __load()
    {
        self::$namespaceLength = strlen(__NAMESPACE__);
        spl_autoload_register(__CLASS__ . '::Load');
    }

    public static function Load($className)
    {
        if (in_array($className, self::$loadingStack)) {
            return false;
        }

        self::$loadingStack[] = $className;
        $returnValue = false;

        if (substr($className, 0, self::$namespaceLength) == __NAMESPACE__) {
            $returnValue = Core::Module(str_replace('\\', '/', substr($className, self::$namespaceLength + 1))) != null;
        }

        array_pop(self::$loadingStack);
        return $returnValue;
    }

    private static $loadingStack = array();
    private static $namespaceLength = 0;
}
