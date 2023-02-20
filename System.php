<?php

namespace YaleREDCap\UserRightsHistory;

use YaleREDCap\UserRightsHistory\Logger;

class System
{
    private $module;
    public $logger;
    public function __construct(UserRightsHistory $module)
    {
        $this->module = $module;
        $this->projectId = $projectId;
        $this->logger = new Logger($module);
    }

    public function updateEnabledByDefaultStatus($currentStatus)
    {
        $lastStatusResult = $this->module->queryLogs("select status where message = ? order by timestamp desc limit 1", ['module enabled by default status']);
        $lastStatus = $lastStatusResult->fetch_assoc()["status"];
        if ($currentStatus != $lastStatus) {
            $this->logger->logEvent('module enabled by default status', ['status' => $currentStatus]);
        }
    }

    public function logVersionIfNeeded()
    {
        $current_version = end(explode("_", $this->module->getModuleDirectoryName()));
        $sql = "select version where message = 'module version' and version = ? order by timestamp desc";
        $result = $this->module->queryLogs($sql, [$current_version]);
        $row = $result->fetch_assoc();
        if (empty($row)) {
            $this->logger->logEvent('module version', ['version' => $current_version, 'current' => json_encode($current_version)]);
        }
    }

    public function getAllProjectIds()
    {
        try {
            $query = "select project_id from redcap_projects
            where created_by is not null
            and completed_time is null
            and date_deleted is null";
            $result = $this->module->query($query, []);
            $project_ids = [];
            while ($row = $result->fetch_assoc()) {
                $project_ids[] = $row["project_id"];
            }
            return $project_ids;
        } catch (\Exception $e) {
            $this->logger->logError("Error fetching all projects", ["error" => $e->getMessage()]);
        }
    }
}
