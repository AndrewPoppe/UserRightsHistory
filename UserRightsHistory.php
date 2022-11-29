<?php

namespace YaleREDCap\UserRightsHistory;

use ExternalModules\AbstractExternalModule;

include_once "Renderer.php";
include_once "UI.php";

class UserRightsHistory extends AbstractExternalModule
{

    public $UI;

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

    function updateAllProjects($cronInfo = array())
    {
        try {
            $enabledSystemwide = $this->getSystemSetting('enabled');

            if ($enabledSystemwide == true) {
                $all_project_ids = $this->getAllProjectIds();
                $project_ids = array_filter($all_project_ids, function ($project_id) {
                    return $this->isModuleEnabled('user_rights_history', $project_id);
                });
            } else {
                $project_ids = $this->getProjectsWithModuleEnabled();
            }

            foreach ($project_ids as $localProjectId) {
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
        $currentProjectInfo = $this->getCurrentProjectInfo($localProjectId) ?? [];
        $lastProjectInfo = $this->getLastProjectInfo($localProjectId) ?? [];
        $changes =  $this->projectInfoChanges($lastProjectInfo, $currentProjectInfo);
        if (is_null($lastProjectInfo) || $changes["any_changes"]) {
            $this->saveProjectInfo($localProjectId, $currentProjectInfo, $changes);
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

    function projectInfoChanges(?array $oldProjectInfo, ?array $newProjectInfo)
    {
        $oldProjectInfo = is_null($oldProjectInfo) ? [] : $oldProjectInfo;
        $newProjectInfo = is_null($newProjectInfo) ? [] : $newProjectInfo;
        $difference1 = array_diff_assoc($oldProjectInfo, $newProjectInfo);
        $difference2 = array_diff_assoc($newProjectInfo, $oldProjectInfo);
        $any_changes = (count($difference1) + count($difference2)) > 0;
        return [
            "previous" => $difference1,
            "current" => $difference2,
            "any_changes" => $any_changes
        ];
    }

    function saveProjectInfo($localProjectId, array $newProjectInfo, array $changes)
    {
        $this->log('project_info', [
            "project_id" => $localProjectId,
            "info" => base64_encode(gzdeflate(json_encode($newProjectInfo), 9)),
            "previous" => json_encode($changes["previous"]),
            "current" => json_encode($changes["current"])
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
            $changes = array("added" => $currentUsers, "removed" => null);
            $this->saveUsers($localProjectId, $currentUsers, $changes);
            return null;
        }
        $userChanges = $this->usersChanged($lastUsers, $currentUsers);
        if ($userChanges["wereChanged"]) {
            $this->saveUsers($localProjectId, $currentUsers, $userChanges);
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
        $result["removed"] = array_diff($oldUsers, $newUsers);
        $result["added"] = array_diff($newUsers, $oldUsers);
        $result["wereChanged"] = count($result["removed"]) > 0 || count($result["added"]) > 0;
        return $result;
    }

    function saveUsers($localProjectId, $users, ?array $changes)
    {
        $this->log('users', [
            "project_id" => $localProjectId,
            "users" => json_encode($users),
            "added" => json_encode($changes["added"]),
            "removed" => json_encode($changes["removed"])
        ]);
    }

    ///////////////////
    // ROLES METHODS //
    ///////////////////

    function updateAllRoles($localProjectId)
    {
        $currentRolesGzip = $this->getCurrentRoles($localProjectId);
        $lastRolesGzip = $this->getLastRoles($localProjectId);
        $rolesChanges = $this->getRolesChanges($lastRolesGzip, $currentRolesGzip);
        if ($rolesChanges["any_changes"]) {
            $this->saveRoles($localProjectId, $currentRolesGzip, $rolesChanges);
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

    function getRolesChanges($lastRolesGzip, $currentRolesGzip)
    {
        $changes = array("any_changes" => false);
        if ($lastRolesGzip !== $currentRolesGzip) {
            $lastRoles = json_decode(gzinflate(base64_decode($lastRolesGzip)), true) ?? [];
            $currentRoles = json_decode(gzinflate(base64_decode($currentRolesGzip)), true) ?? [];
            $changes["previous"] = array();
            foreach ($lastRoles as $index => $lastRole) {
                if (empty($currentRoles[$index])) {
                    $changes["previous"][$index] = $lastRole;
                } else {
                    $changes["previous"][$index] = array_diff_assoc($lastRole, $currentRoles[$index]);
                }
            }
            $changes["current"] = array();
            foreach ($currentRoles as $index => $currentRole) {
                if (empty($lastRoles[$index])) {
                    $changes["current"][$index] = $currentRole;
                } else {
                    $changes["current"][$index] = array_diff_assoc($currentRole, $lastRoles[$index]);
                }
            }
            $changes["any_changes"] = true;
        }
        return $changes;
    }

    function saveRoles($localProjectId, $rolesGzip, $changes)
    {
        $this->log('roles', [
            "project_id" => $localProjectId,
            "roles" => $rolesGzip,
            "previous" => json_encode($changes["previous"]),
            "current" => json_encode($changes["current"])
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
        $currentPermissions_gzip = $this->getCurrentUserPermissions($localProjectId, $user);
        $lastPermissions_gzip = $this->getLastUserPermissions($localProjectId, $username);
        $permissionsChanges = $this->getPermissionsChanges($lastPermissions_gzip, $currentPermissions_gzip, $username);
        if ($lastPermissions_gzip == null || $permissionsChanges["any_changes"]) {
            $this->savePermissions($localProjectId, $currentPermissions_gzip, $permissionsChanges, $username);
        }
    }

    /**
     * @param user $user user object from EM framework
     * 
     * @return string permissions, including name and account status
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

        return base64_encode(gzdeflate(json_encode($rights), 9));
    }

    function getLastUserPermissions($localProjectId, string $username)
    {

        $sql = "select rights where message = 'rights' and user_name = ? and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$username, $localProjectId]);
        $rights_gzip = $result->fetch_assoc()["rights"];
        return $rights_gzip;
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

    function getPermissionsChanges(?string $oldPermissions_gzip, ?string $newPermissions_gzip, $username)
    {
        $changes = array("any_changes" => false);
        if ($oldPermissions_gzip !== $newPermissions_gzip) {
            $changes["any_changes"] = true;
            $oldPermissions = json_decode(gzinflate(base64_decode($oldPermissions_gzip)), true) ?? [];
            $newPermissions = json_decode(gzinflate(base64_decode($newPermissions_gzip)), true);

            $changes["previous"] = array_diff_assoc($oldPermissions, $newPermissions);
            $changes["current"] = array_diff_assoc($newPermissions, $oldPermissions);

            $possibleDagChanges_previous = array_diff_assoc($oldPermissions["possibleDags"], $newPermissions["possibleDags"]);
            $possibleDagChanges_current = array_diff_assoc($newPermissions["possibleDags"], $oldPermissions["possibleDags"]);
            if (count($possibleDagChanges_previous) > 0 || count($possibleDagChanges_current) > 0) {
                $changes["previous"]["possibleDags"] = $possibleDagChanges_previous;
                $changes["current"]["possibleDags"] = $possibleDagChanges_current;
            }
        }
        return $changes;
    }

    function savePermissions($localProjectId, string $newPermissions_gzip, array $permissionsChanges, $username)
    {
        $this->log('rights', [
            "user_name" => $username,
            "project_id" => $localProjectId,
            "rights" => $newPermissions_gzip,
            "previous" => json_encode($permissionsChanges["previous"]),
            "current" => json_encode($permissionsChanges["current"])
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
        $dagChanges = $this->getDAGsChanges($lastDAGsGzip, $currentDAGsGzip);
        if ($dagChanges["any_changes"]) {
            $this->saveDAGs($localProjectId, $currentDAGsGzip, $dagChanges);
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

    function getDAGsChanges($lastDAGsGzip, $currentDAGsGzip)
    {
        $changes = array("any_changes" => false);
        if ($lastDAGsGzip !== $currentDAGsGzip) {
            $changes["any_changes"] = true;
            $lastDAGs = json_decode(gzinflate(base64_decode($lastDAGsGzip)), true) ?? [];
            $currentDAGs = json_decode(gzinflate(base64_decode($currentDAGsGzip)), true) ?? [];
            $changes["previous"] = array();
            foreach ($lastDAGs as $index => $lastDAG) {
                if (empty($currentDAGs[$index])) {
                    $changes["previous"][$index] = $lastDAG;
                } else {
                    $changes["previous"][$index] = array_diff_assoc($lastDAG, $currentDAGs[$index]);
                }
            }
            $changes["current"] = array();
            foreach ($currentDAGs as $index => $currentDAG) {
                if (empty($lastDAGs[$index])) {
                    $changes["current"][$index] = $currentDAG;
                } else {
                    $changes["current"][$index] = array_diff_assoc($currentDAG, $lastDAGs[$index]);
                }
            }
        }
        return $changes;
    }

    function saveDAGs($localProjectId, $dagsGzip, $dagChanges)
    {
        $this->log('dags', [
            "project_id" => $localProjectId,
            "dags" => $dagsGzip,
            "current" => json_encode($dagChanges["current"]),
            "previous" => json_encode($dagChanges["previous"])
        ]);
    }

    ////////////////////
    // System Methods //
    ////////////////////

    function updateAllSystem()
    {
        try {
            $currentSystemGzip = $this->getCurrentSystem();
            $lastSystemGzip = $this->getLastSystem();
            $systemChanges = $this->getSystemChanges($lastSystemGzip, $currentSystemGzip);
            if (empty($lastSystemGzip) || $systemChanges["any_changes"]) {
                $this->saveSystem($currentSystemGzip, $systemChanges);
            }
        } catch (\Exception $e) {
            $this->log("Error updating system",  [
                "error" => $e->getMessage()
            ]);
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
            $this->log("Error getting current system",  [
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

    function getSystemChanges($lastSystemGzip, $currentSystemGzip)
    {
        $changes = array("any_changes" => false);
        if ($lastSystemGzip !== $currentSystemGzip) {
            $changes["any_changes"] = true;
            $lastSystem = json_decode(gzinflate(base64_decode($lastSystemGzip)), true) ?? [];
            $currentSystem = json_decode(gzinflate(base64_decode($currentSystemGzip)), true) ?? [];
            $changes["previous"] = array_diff_assoc($lastSystem, $currentSystem);
            $changes["current"] = array_diff_assoc($currentSystem, $lastSystem);
        }
        return $changes;
    }

    function saveSystem($system_gzip, $systemChanges)
    {
        $this->log('system', [
            "info" => $system_gzip,
            "current" => json_encode($systemChanges["current"]),
            "previous" => json_encode($systemChanges["previous"])
        ]);
    }

    ////////////////////////
    // Instrument Methods //
    ////////////////////////

    function updateAllInstruments($localProjectId)
    {
        $currentInstrumentsGzip = $this->getCurrentInstruments($localProjectId);
        $lastInstrumentsGzip = $this->getLastInstruments($localProjectId);
        $instrumentsChanges = $this->getInstrumentsChanges($lastInstrumentsGzip, $currentInstrumentsGzip);
        if ($instrumentsChanges["any_changes"]) {
            $this->saveInstruments($localProjectId, $currentInstrumentsGzip, $instrumentsChanges);
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

    function getInstrumentsChanges($lastInstrumentsGzip, $currentInstrumentsGzip)
    {
        $changes = array("any_changes" => false);
        if ($lastInstrumentsGzip !== $currentInstrumentsGzip) {
            $changes["any_changes"] = true;
            $lastInstruments = json_decode(gzinflate(base64_decode($lastInstrumentsGzip)), true) ?? [];
            $currentInstruments = json_decode(gzinflate(base64_decode($currentInstrumentsGzip)), true) ?? [];
            $changes["previous"] = array_diff_assoc($lastInstruments, $currentInstruments);
            $changes["current"] = array_diff_assoc($currentInstruments, $lastInstruments);
        }
        return $changes;
    }

    function saveInstruments($localProjectId, $instruments_gzip, $instrumentsChanges)
    {
        $this->log('instruments', [
            "project_id" => $localProjectId,
            "instruments" => $instruments_gzip,
            "previous" => json_encode($instrumentsChanges["previous"]),
            "current" => json_encode($instrumentsChanges["current"])
        ]);
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
        return $results;
    }


    function getEarliestLogTimestamp()
    {
        $sql = "select UNIX_TIMESTAMP(timestamp) order by timestamp limit 1";
        $result = $this->queryLogs($sql, []);
        $timestamp = $result->fetch_assoc()["UNIX_TIMESTAMP(redcap_external_modules_log.timestamp)"];
        return $timestamp * 1000 + 60000;
    }

    ///////////////////////////
    // Logging Table Methods //
    ///////////////////////////

    function syntaxHighlight($json)
    {
        $json = json_encode(json_decode($json), JSON_PRETTY_PRINT);
        $json = preg_replace('/&/', '&amp;', $json);
        $json = preg_replace('/</', '&lt;', $json);
        $json = preg_replace('/>/', '&gt;', $json);
        $result = preg_replace_callback(
            '/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/',
            function ($matches) {
                $cls = "number";
                if (preg_match('/^"/', $matches[1])) {
                    if (preg_match('/:$/', $matches[1])) {
                        $cls = "key";
                    } else {
                        $cls = "string";
                    }
                } else if (preg_match('/true|false/', $matches[1])) {
                    $cls = "boolean";
                } else if (preg_match('/null/', $matches[1])) {
                    $cls = "null";
                }
                return '<span class="' . $cls . '">' . $matches[1] . '</span>';
            },
            $json
        );
        return $result;
    }

    function getTotalLogCount()
    {
        try {
            $startTime = microtime();
            $queryResult = $this->queryLogs("select count(timestamp) ts where (project_id = ? or project_id is null) and message in (
                'rights', 
                'instruments',
                'roles',
                'rights',
                'system',
                'dags',
                'project_info',
                'users'
            )", [$this->getProjectId()]);
            $endTime = microtime();
            $this->log('process time: total logs', ["time" => $endTime - $startTime]);
            return $queryResult->fetch_assoc()["ts"];
        } catch (\Exception $e) {
            $this->log('Error getting total log count', ["error" => $e->getMessage()]);
            return;
        }
    }

    function getLogs(array $params)
    {
        try {
            $start = intval($params["start"]);
            $length = intval($params["length"]);

            $searchTerm = $params["search"]["value"] ?? "";
            $searchText = $searchTerm === "" ? "" : " and (timestamp like '%" . $searchTerm . "%' or message like '%" . $searchTerm . "%' or current like '%" . $searchTerm . "%' or previous like '%" . $searchTerm . "%')";

            $orderTerm = "";
            foreach ($params["order"] as $index => $order) {
                $column = $params["columns"][intval($order["column"])]["data"];
                $direction = $order["dir"] === "asc" ? "asc" : "desc";
                if ($index === 0) {
                    $orderTerm .= " order by ";
                }
                $orderTerm .= $column . " " . $direction;
                if ($index !== sizeof($params["order"]) - 1) {
                    $orderTerm .= ", ";
                }
            }
            if ($orderTerm === "") {
                $orderTerm = " order by timestamp desc";
            }

            $queryText =  "select timestamp, message, current, previous where (project_id = ? or project_id is null) and message in (
                'rights', 
                'instruments',
                'roles',
                'rights',
                'system',
                'dags',
                'project_info',
                'users'
            )" . $searchText . $orderTerm . " limit " . $start . "," . $length;
            $countText =  "select count(timestamp) ts where (project_id = ? or project_id is null) and message in (
                'rights', 
                'instruments',
                'roles',
                'rights',
                'system',
                'dags',
                'project_info',
                'users'
            )" . $searchText;

            $startTime = microtime();
            $queryResult = $this->queryLogs(
                $queryText,
                [
                    $this->getProjectId(), // project id
                ]
            );
            $countResult = $this->queryLogs(
                $countText,
                [
                    $this->getProjectId()
                ]
            );
            $rowsTotal = $countResult->fetch_assoc()["ts"];
            $endTime = microtime();
            $logs = array();
            while ($row = $queryResult->fetch_assoc()) {
                $row["previous"] = $this->syntaxHighlight($row["previous"]);
                $row["current"] = $this->syntaxHighlight($row["current"]);
                $logs[] = $row;
            }
            $endTime2 = microtime();
            $this->log("process time: get logs", ["time" => $endTime - $startTime]);
            $this->log("process time: format logs", ["time" => $endTime2 - $endTime]);
            return [$logs, $rowsTotal];
        } catch (\Exception $e) {
            $this->log('Error getting logs', ["error" => $e->getMessage()]);
        }
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

    function showPageHeader(string $page)
    {
        $UI = new UI($this);
        $UI->showPageHeader($page);
    }
}
