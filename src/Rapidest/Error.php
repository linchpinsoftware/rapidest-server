<?php

namespace Rapidest;

class Error extends \Error
{
    public function handleException(\Error $e)
    {
        die($e->getMessage() . $e->getTraceAsString());
    }
    
    public function handleError(int $errno, string $errstr, string $errfile = null, int $errline = null, array $errcontext = null)
    {
        switch ($errno) {
            case E_USER_ERROR:
            /** @todo turn off in production environment */
            //case E_USER_WARNING:
            //case E_USER_NOTICE:
            //default:
                die(json_encode(['error' => "<b>My ERROR</b> [$errno] $errstr<br />\n  Fatal error on line $errline in file $errfile, PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\nAborting...<br />\n"]));
                break;
        }
        /* Don't execute PHP internal error handler */
        return true;
    }
}