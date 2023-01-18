<?php

namespace YaleREDCap\UserRightsHistory;

use ExternalModules\AbstractExternalModule;

include_once "Renderer.php";

class UserRightsHistory extends AbstractExternalModule
{

    function redcap_module_system_change_version($version, $old_version)
    {
        $this->log('module version', ['previous' => json_encode($old_version), 'current' => json_encode($version), 'version' => $version]);
    }

    function redcap_module_project_enable($version, $project_id)
    {
        $this->log('module project status', [
            "version" => $version,
            "status" => 1,
            "project_id" => $project_id,
            "previous" => json_encode("Disabled"),
            "current" => json_encode("Enabled")
        ]);
    }

    function redcap_module_project_disable($version, $project_id)
    {
        $this->log('module project status', [
            "version" => $version,
            "status" => 0,
            "project_id" => $project_id,
            "previous" => json_encode("Enabled"),
            "current" => json_encode("Disabled")
        ]);
    }

    function redcap_module_system_enable($version)
    {
        $this->log('module system status', [
            "version" => $version,
            "status" => 1
        ]);
    }

    function redcap_module_system_disable($version)
    {
        $this->log('module system status', [
            "version" => $version,
            "status" => 0
        ]);
    }

    function updateEnabledByDefaultStatus($currentStatus)
    {
        $lastStatusResult = $this->queryLogs("select status where message = ? order by timestamp desc limit 1", ['module enabled by default status']);
        $lastStatus = $lastStatusResult->fetch_assoc()["status"];
        if ($currentStatus != $lastStatus) {
            $this->log('module enabled by default status', ['status' => $currentStatus]);
        }
    }

    function getAllProjectIds()
    {
        try {
            $query = "select project_id from redcap_projects
            where created_by is not null
            and completed_time is null
            and date_deleted is null";
            $result = $this->query($query, []);
            $project_ids = [];
            while ($row = $result->fetch_assoc()) {
                $project_ids[] = $row["project_id"];
            }
            return $project_ids;
        } catch (\Exception $e) {
            $this->log("Error fetching all projects", ["error" => $e->getMessage()]);
        }
    }

    function logVersionIfNeeded()
    {
        $current_version = end(explode("_", $this->getModuleDirectoryName()));
        $sql = "select version where message = 'module version' and version = ? order by timestamp desc";
        $result = $this->queryLogs($sql, [$current_version]);
        $row = $result->fetch_assoc();
        if (empty($row)) {
            $this->log('module version', ['version' => $current_version, 'current' => json_encode($current_version)]);
        }
    }

    function updateProjectStatusMessageIfNeeded($localProjectId)
    {
        $sql = "select timestamp where message = 'module project status' and project_id = ?";
        $result = $this->queryLogs($sql, [$localProjectId]);
        $row = $result->fetch_assoc();
        if (empty($row) && $this->isModuleEnabled('user_rights_history', $localProjectId)) {
            $this->log('module project status', [
                "project_id" => $localProjectId,
                "status" => 1,
                "current" => json_encode("Enabled")
            ]);
        }
    }

    function updateAllProjects($cronInfo = array())
    {
        try {
            $enabledSystemwide = $this->getSystemSetting('enabled');
            $this->updateEnabledByDefaultStatus($enabledSystemwide);

            // log new versions manually (in case version change hook doesn't work)
            $this->logVersionIfNeeded();

            if ($enabledSystemwide == true) {
                $all_project_ids = $this->getAllProjectIds();
                $project_ids = array_filter($all_project_ids, function ($project_id) {
                    return $this->isModuleEnabled('user_rights_history', $project_id);
                });
            } else {
                $project_ids = $this->getProjectsWithModuleEnabled();
            }

            foreach ($project_ids as $localProjectId) {
                // Ensure a project status message appears for this module.
                $this->updateProjectStatusMessageIfNeeded($localProjectId);

                $this->updateUserList($localProjectId);
                $this->updateProjectInfo($localProjectId);
                $this->updatePermissionsForAllUsers($localProjectId);
                $this->updateAllRoles($localProjectId);
                $this->updateAllDAGs($localProjectId);
                $this->updateAllInstruments($localProjectId);
            }
            $this->updateAllSystem();
            return "The \"{$cronInfo['cron_name']}\" cron job completed successfully.";
        } catch (\Exception $e) {
            $this->log("Error updating projects", ["error" => $e->getMessage()]);
            return "The \"{$cronInfo['cron_name']}\" cron job failed: " . $e->getMessage();
        }
    }

    ////////////////////////////
    // PROJECT STATUS METHODS //
    ////////////////////////////

    function updateProjectInfo($localProjectId)
    {
        $currentProjectInfo = $this->getCurrentProjectInfo($localProjectId);
        $lastProjectInfo = $this->getLastProjectInfo($localProjectId);
        if ($lastProjectInfo == null || $this->projectInfoChanged($lastProjectInfo, $currentProjectInfo)) {
            $this->saveProjectInfo($localProjectId, $currentProjectInfo);
        }
    }

    function getCurrentProjectInfo($localProjectId)
    {
        try {
            $sql = "select * from redcap_projects where project_id = ?";
            $result = $this->query($sql, [$localProjectId]);
            $result_array = $result->fetch_assoc();
            unset($result_array["last_logged_event"]); // Prevent unncessary updates
            return $result_array;
        } catch (\Exception $e) {
            $this->log('Error fetching project info', ['error' => $e->getMessage()]);
        }
    }

    function getLastProjectInfo($localProjectId)
    {
        $sql = "select info where message = 'project_info' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$localProjectId]);
        $info_gzip = $result->fetch_assoc()["info"];
        return json_decode(gzinflate(base64_decode($info_gzip)), true);
    }

    function projectInfoChanged(array $oldProjectInfo, array $newProjectInfo)
    {
        $difference1 = array_diff_assoc($oldProjectInfo, $newProjectInfo);
        $difference2 = array_diff_assoc($newProjectInfo, $oldProjectInfo);
        return (count($difference1) + count($difference2)) > 0;
    }

    function saveProjectInfo($localProjectId, array $newProjectInfo)
    {
        $this->log('project_info', [
            "project_id" => $localProjectId,
            "info" => base64_encode(gzdeflate(json_encode($newProjectInfo), 9))
        ]);
    }

    ///////////////////////
    // USER LIST METHODS //
    ///////////////////////

    function getCurrentUsers($localProjectId)
    {
        $project = $this->getProject($localProjectId);
        $users = $project->getUsers();
        $result = array();
        foreach ($users as $user) {
            $result[] = $user->getUsername();
        }
        return $result;
    }

    function updateUserList($localProjectId)
    {
        $currentUsers = $this->getCurrentUsers($localProjectId);
        $lastUsers = $this->getLastUsers($localProjectId);
        if ($lastUsers == null) {
            $this->saveUsers($localProjectId, $currentUsers);
            return null;
        }
        $userChanges = $this->usersChanged($lastUsers, $currentUsers);
        if ($userChanges["wereChanged"]) {
            $this->saveUsers($localProjectId, $currentUsers);
        }
        if (count($userChanges["removed"]) > 0) {
            $this->markUsersRemoved($localProjectId, $userChanges["removed"]);
        }
    }

    function getLastUsers($localProjectId)
    {
        $sql = "select users where message = 'users' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$localProjectId]);
        $users_json = $result->fetch_assoc()["users"];
        return json_decode($users_json, true);
    }

    function usersChanged($oldUsers, $newUsers)
    {
        $result = array();
        $result["removed"] =  array_diff($oldUsers, $newUsers);
        $result["added"] = array_diff($newUsers, $oldUsers);
        $result["wereChanged"] = count($result["removed"]) > 0 || count($result["added"]) > 0;
        return $result;
    }

    function saveUsers($localProjectId, $users)
    {
        $this->log('users', [
            "project_id" => $localProjectId,
            "users" => json_encode($users)
        ]);
    }

    ///////////////////
    // ROLES METHODS //
    ///////////////////

    function updateAllRoles($localProjectId)
    {
        $currentRolesGzip = $this->getCurrentRoles($localProjectId);
        $lastRolesGzip = $this->getLastRoles($localProjectId);
        if ($this->rolesChanged($lastRolesGzip, $currentRolesGzip)) {
            $this->saveRoles($localProjectId, $currentRolesGzip);
        }
    }

    function getCurrentRoles($localProjectId)
    {
        try {
            $sql = "select * from redcap_user_roles where project_id = ?";
            $result = $this->query($sql, [$localProjectId]);
            $roles = array();
            while ($role = $result->fetch_assoc()) {
                $roles[$role["role_id"]] = $role;
            }
            return !empty($roles) ? base64_encode(gzdeflate(json_encode($roles), 9)) : null;
        } catch (\Exception $e) {
            $this->log("Error updating roles",  [
                "project_id" => $localProjectId,
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastRoles($localProjectId)
    {
        $sql = "select roles where message = 'roles' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$localProjectId]);
        return $result->fetch_assoc()["roles"];
    }

    function rolesChanged($lastRolesGzip, $currentRolesGzip)
    {
        return $lastRolesGzip !== $currentRolesGzip;
    }

    function saveRoles($localProjectId, $rolesGzip)
    {
        $this->log('roles', [
            "project_id" => $localProjectId,
            "roles" => $rolesGzip
        ]);
    }



    /////////////////////////
    // USER RIGHTS METHODS //
    /////////////////////////

    function updatePermissionsForAllUsers($localProjectId)
    {
        try {
            $project = $this->getProject($localProjectId);
            $users = $project->getUsers();
            foreach ($users as $user) {
                $this->updatePermissionsForUser($localProjectId, $user);
            }
        } catch (\Exception $e) {
            $this->log("Error updating users",  [
                "project_id" => $localProjectId,
                "error" => $e->getMessage()
            ]);
        }
    }

    /**
     * Checks current rights with last saved rights; if they have changed, 
     * save the current rights to the log.
     * 
     * @param user $user user object from EM framework
     * 
     * @return void
     */
    function updatePermissionsForUser($localProjectId, $user)
    {
        $username = $user->getUsername();
        $currentPermissions = $this->getCurrentUserPermissions($localProjectId, $user);
        $lastPermissions = $this->getLastUserPermissions($localProjectId, $username);
        if ($lastPermissions == null || $this->permissionsChanged($lastPermissions, $currentPermissions)) {
            $this->savePermissions($localProjectId, $currentPermissions);
        }
    }

    /**
     * @param user $user user object from EM framework
     * 
     * @return array permissions, including name and account status
     */
    function getCurrentUserPermissions($localProjectId, $user)
    {

        $username = $user->getUsername();
        $name = $this->getName($username);
        $email = $user->getEmail();
        $suspended = $this->getStatus($username);
        $rights = $user->getRights($localProjectId);
        $isSuperUser = $user->isSuperUser();
        $possibleDags = $this->getPossibleDags($localProjectId, $username);

        $rights["name"] = $name;
        $rights["email"] = $email;
        $rights["suspended"] = $suspended;
        $rights["isSuperUser"] = $isSuperUser;
        $rights["possibleDags"] = $possibleDags;

        return $rights;
    }

    function getLastUserPermissions($localProjectId, string $username)
    {

        $sql = "select rights where message = 'rights' and user_name = ? and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$username, $localProjectId]);
        $rights_gzip = $result->fetch_assoc()["rights"];
        return json_decode(gzinflate(base64_decode($rights_gzip)), true);
    }

    function getName(string $username)
    {
        try {
            $sql = "select concat(user_firstname, ' ', user_lastname) name
            from redcap_user_information
            where username = ?";
            $result = $this->query($sql, [$username]);
            return $result->fetch_assoc()["name"];
        } catch (\Exception $e) {
            $this->log("Error fetching name", ["error" => $e->getMessage()]);
        }
    }

    function getPossibleDags($localProjectId, string $username)
    {
        try {
            $sql = "select group_id from redcap_data_access_groups_users where project_id = ? and username = ?";
            $result = $this->query($sql, [$localProjectId, $username]);
            $possibleDags = array();
            while ($row = $result->fetch_assoc()) {
                $possibleDags[] = $row["group_id"];
            }
            return $possibleDags;
        } catch (\Exception $e) {
            $this->log("Error fetching possible dags", ["error" => $e->getMessage()]);
        }
    }

    function getStatus(string $username)
    {
        try {
            $sql = "select user_suspended_time < NOW() suspended
            from redcap_user_information
            where username = ?";
            $result = $this->query($sql, [$username]);
            return $result->fetch_assoc()["suspended"] === 1;
        } catch (\Exception $e) {
            $this->log("Error fetching status", ["error" => $e->getMessage()]);
        }
    }

    function permissionsChanged(array $oldPermissions, array $newPermissions)
    {
        $difference1 = array_diff_assoc($oldPermissions, $newPermissions);
        $difference2 = array_diff_assoc($newPermissions, $oldPermissions);
        return count($difference1) > 0 || count($difference2) > 0;
    }

    function savePermissions($localProjectId, array $newPermissions)
    {
        $this->log('rights', [
            "user_name" => $newPermissions["username"],
            "project_id" => $localProjectId,
            "rights" => base64_encode(gzdeflate(json_encode($newPermissions), 9))
        ]);
    }

    function markUsersRemoved($localProjectId, $removed_users)
    {
        foreach ($removed_users as $username) {
            $this->log('rights', [
                "user_name" => $username,
                "rights" => null,
                "status" => "removed",
                "project_id" => $localProjectId
            ]);
        }
    }


    /////////////////
    // DAG Methods //
    /////////////////


    function updateAllDAGs($localProjectId)
    {
        $currentDAGsGzip = $this->getCurrentDAGs($localProjectId);
        $lastDAGsGzip = $this->getLastDAGs($localProjectId);
        if ($this->dagsChanged($lastDAGsGzip, $currentDAGsGzip)) {
            $this->saveDAGs($localProjectId, $currentDAGsGzip);
        }
    }

    function getCurrentDAGs($localProjectId)
    {
        try {
            $sql = "select * from redcap_data_access_groups where project_id = ?";
            $result = $this->query($sql, [$localProjectId]);
            $dags = array();
            while ($dag = $result->fetch_assoc()) {
                $dags[$dag["group_id"]] = $dag;
            }
            return !empty($dags) ? base64_encode(gzdeflate(json_encode($dags), 9)) : null;
        } catch (\Exception $e) {
            $this->log("Error updating dags",  [
                "project_id" => $localProjectId,
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastDAGs($localProjectId)
    {
        $sql = "select dags where message = 'dags' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$localProjectId]);
        return $result->fetch_assoc()["dags"];
    }

    function dagsChanged($lastDAGsGzip, $currentDAGsGzip)
    {
        return $lastDAGsGzip !== $currentDAGsGzip;
    }

    function saveDAGs($localProjectId, $dagsGzip)
    {
        $this->log('dags', [
            "project_id" => $localProjectId,
            "dags" => $dagsGzip
        ]);
    }

    ////////////////////
    // System Methods //
    ////////////////////

    function updateAllSystem()
    {
        $currentSystemGzip = $this->getCurrentSystem();
        $lastSystemGzip = $this->getLastSystem();
        if ($this->systemChanged($lastSystemGzip, $currentSystemGzip)) {
            $this->saveSystem($currentSystemGzip);
        }
    }

    function getCurrentSystem()
    {
        try {
            $sql = "select * from redcap_config";
            $result = $this->query($sql, []);
            $fields = ["enable_plotting", "dts_enabled_global", "api_enabled", "mobile_app_enabled"];
            $system_info = array();
            while ($row = $result->fetch_assoc()) {
                if (in_array($row["field_name"], $fields, true)) {
                    $system_info[$row["field_name"]] = $row["value"];
                }
            }
            return base64_encode(gzdeflate(json_encode($system_info), 9));
        } catch (\Exception $e) {
            $this->log("Error updating system",  [
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastSystem()
    {
        $sql = "select info where message = 'system' order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, []);
        return $result->fetch_assoc()["info"];
    }

    function systemChanged($lastSystemGzip, $currentSystemGzip)
    {
        return $lastSystemGzip !== $currentSystemGzip;
    }

    function saveSystem($system_gzip)
    {
        $this->log('system', [
            "info" => $system_gzip
        ]);
    }

    ////////////////////////
    // Instrument Methods //
    ////////////////////////

    function updateAllInstruments($localProjectId)
    {
        $currentInstrumentsGzip = $this->getCurrentInstruments($localProjectId);
        $lastInstrumentsGzip = $this->getLastInstruments($localProjectId);
        if ($this->instrumentsChanged($lastInstrumentsGzip, $currentInstrumentsGzip)) {
            $this->saveInstruments($localProjectId, $currentInstrumentsGzip);
        }
    }

    function getCurrentInstruments($localProjectId)
    {
        try {
            $sql = "select m.form_name id,max(m.form_menu_description) title, s.survey_id from redcap_metadata m
            left join redcap_surveys s
            on m.form_name = s.form_name
            and m.project_id = s.project_id
            where m.project_id = ?
            group by m.project_id, m.form_name
            order by m.field_order";
            $result = $this->query($sql, [$localProjectId]);
            $instruments = [];
            while ($instrument = $result->fetch_assoc()) {
                $instruments[$instrument["id"]] = $instrument;
            }
            return base64_encode(gzdeflate(json_encode($instruments), 9));
        } catch (\Exception $e) {
            $this->log("Error updating instruments",  [
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastInstruments($localProjectId)
    {
        $sql = "select instruments where message = 'instruments' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$localProjectId]);
        return $result->fetch_assoc()["instruments"];
    }

    function instrumentsChanged($lastInstrumentsGzip, $currentInstrumentsGzip)
    {
        return $lastInstrumentsGzip !== $currentInstrumentsGzip;
    }

    function saveInstruments($localProjectId, $instruments_gzip)
    {
        $this->log('instruments', [
            "project_id" => $localProjectId,
            "instruments" => $instruments_gzip
        ]);
    }

    ///////////////////////////
    // Module Status Methods //
    ///////////////////////////

    function getModuleSystemStatusByTimestamp($timestamp_clean)
    {
        $system_status_sql = "select status where message = 'module system status' and project_id is null";
        $system_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $system_status_sql .= " order by timestamp desc limit 1";
        $system_status_result = $this->queryLogs($system_status_sql, [$timestamp_clean]);
        $status = $system_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleProjectStatusByTimestamp($timestamp_clean)
    {
        $project_status_sql = "select status where message = 'module project status'";
        $project_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $project_status_sql .= " order by timestamp desc limit 1";
        $project_status_result = $this->queryLogs($project_status_sql, [$timestamp_clean]);
        $status = $project_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleDefaultEnabledByTimestamp($timestamp_clean)
    {
        $default_status_sql = "select status where message = 'module enabled by default status' and project_id is null";
        $default_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $default_status_sql .= " order by timestamp desc limit 1";
        $default_status_result = $this->queryLogs($default_status_sql, [$timestamp_clean]);
        $status = $default_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleStatusByTimestamp($timestamp_clean)
    {
        $system_status = $this->getModuleSystemStatusByTimestamp($timestamp_clean);
        if ($system_status === 0) {
            return 0;
        }

        $project_status = $this->getModuleProjectStatusByTimestamp($timestamp_clean);
        if ($project_status === 1) {
            return 1;
        } else if ($project_status === 0) {
            return 0;
        }

        return $this->getModuleDefaultEnabledByTimestamp($timestamp_clean);
    }


    ////////////////////////////
    // Module Version Methods //
    ////////////////////////////

    /**
     * Get the timestamp of the first 'module version' log
     * 
     * This is useful, as the adoption of some module functions coincided with these logs
     * @return int
     */
    function getFirstModuleVersionTimestamp()
    {
        $sql = "select UNIX_TIMESTAMP(timestamp) where message = 'module version' and project_id is null order by timestamp limit 1";
        $result = $this->queryLogs($sql, []);
        $timestamp = $result->fetch_assoc()["UNIX_TIMESTAMP(redcap_external_modules_log.timestamp)"];
        return intval($timestamp);
    }

    function isTimestampOld($timestamp_clean)
    {
        $sql = "message = 'module version' and project_id is null";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $result = intval($this->countLogs($sql, [$timestamp_clean]));
        return $result === 0;
    }


    ///////////////////////
    // Filtering Methods //
    ///////////////////////

    function getUserPermissionsByTimestamp($username, $timestamp_clean)
    {
        $sql = "select rights where message = 'rights' and user_name = ?";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$username, $timestamp_clean]);
        $rights_gzip = $result->fetch_assoc()["rights"];
        return json_decode(gzinflate(base64_decode($rights_gzip)), true);
    }

    function getUsersByTimestamp($timestamp_clean)
    {
        $sql = "select users where message = 'users'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $users_json = $result->fetch_assoc()["users"];
        return json_decode($users_json, true);
    }

    private function getValueByTimestamp($timestamp_clean, $message, $column, $system = false)
    {
        $sql = "select $column where message = ?";
        $sql .= $system ? " and project_id is null" : "";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$message, $timestamp_clean]);
        $gzip = $result->fetch_assoc()[$column];
        return json_decode(gzinflate(base64_decode($gzip)), true);
    }

    function getProjectStatusByTimestamp($timestamp_clean)
    {
        return $this->getValueByTimestamp($timestamp_clean, 'project_info', 'info');
    }

    function getAllRolesByTimestamp($timestamp_clean)
    {
        return $this->getValueByTimestamp($timestamp_clean, 'roles', 'roles');
    }

    function getAllDAGsByTimestamp($timestamp_clean)
    {
        return $this->getValueByTimestamp($timestamp_clean, 'dags', 'dags');
    }

    function getAllSystemByTimestamp($timestamp_clean)
    {
        return $this->getValueByTimestamp($timestamp_clean, 'system', 'info', true);
    }

    function getAllInstrumentsByTimestamp($timestamp_clean)
    {
        return $this->getValueByTimestamp($timestamp_clean, 'instruments', 'instruments');
    }

    function getAllInfoByTimestamp($timestamp = null)
    {
        $results = array();
        $results["timestamp"] = intval($timestamp) / 1000;

        $users = $this->getUsersByTimestamp($results["timestamp"]);
        $results["users"] = array();
        foreach ($users as $user) {
            $results["users"][$user] = $this->getUserPermissionsByTimestamp($user, $results["timestamp"]);
        }

        $results["dags"] = $this->getAllDAGsByTimestamp($results["timestamp"]);
        $results["roles"] = $this->getAllRolesByTimestamp($results["timestamp"]);
        $results["project_status"] = $this->getProjectStatusByTimestamp($results["timestamp"]);
        $results["system"] = $this->getAllSystemByTimestamp($results["timestamp"]);
        $results["instruments"] = $this->getAllInstrumentsByTimestamp($results["timestamp"]);

        $results["module_status"] = $this->getModuleStatusByTimestamp($results["timestamp"]);
        $results["old"] = $this->isTimestampOld($results["timestamp"]);
        return $results;
    }


    function getEarliestLogTimestamp()
    {
        $sql = "select UNIX_TIMESTAMP(timestamp) order by timestamp limit 1";
        $result = $this->queryLogs($sql, []);
        $timestamp = $result->fetch_assoc()["UNIX_TIMESTAMP(redcap_external_modules_log.timestamp)"];
        return $timestamp * 1000 + 60000;
    }



    /////////////////////
    // Display Methods //
    /////////////////////

    function renderTable(array $permissions)
    {
        $Renderer = new Renderer($permissions);
        try {
            $Renderer->renderTable();
        } catch (\Exception $e) {
            $this->log('Error rendering table', ['message' => $e->getMessage()]);
        }
    }
}
