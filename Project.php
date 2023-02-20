<?php

namespace YaleREDCap\UserRightsHistory;

use YaleREDCap\UserRightsHistory\Logger;

class Project
{
    private $module;
    private $projectId;
    private $logger;
    public function __construct(UserRightsHistory $module, $projectId)
    {
        $this->module = $module;
        $this->projectId = $projectId;
        $this->logger = new Logger($module);
    }

    function updateProjectStatusMessageIfNeeded()
    {
        $sql = "select timestamp where message = 'module project status' and project_id = ?";
        $result = $this->module->queryLogs($sql, [$this->projectId]);
        $row = $result->fetch_assoc();
        if (empty($row) && in_array($this->projectId, $this->module->getProjectsWithModuleEnabled())) {
            $this->logger->logEvent('module project status', [
                "project_id" => $this->projectId,
                "status" => 1,
                "current" => json_encode("Enabled")
            ]);
        }
    }
}
