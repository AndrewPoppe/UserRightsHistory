<?php

namespace YaleREDCap\UserRightsHistory;

class Logger
{

    private $module;
    public function __construct(UserRightsHistory $module)
    {
        $this->module = $module;
    }

    /////////////////////
    // Logging Methods //
    /////////////////////

    // For now, these are identical
    // Separating them for future development
    public function logEvent(string $message, array $args = [])
    {
        $this->module->log($message, $args);
    }
    public function logError(string $message, array $args = [])
    {
        $this->module->log($message, $args);
    }
}
