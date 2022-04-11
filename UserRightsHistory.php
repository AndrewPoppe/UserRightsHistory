<?php

namespace YaleREDCap\UserRightsHistory;

use ExternalModules\AbstractExternalModule;

include_once "Renderer.php";

class UserRightsHistory extends AbstractExternalModule
{
    function redcap_every_page_before_render()
    {
    }

    function redcap_user_rights()
    {
        var_dump($this->getUrl('history_viewer.php', true));
    }

    function updateAllProjects($cronInfo = array())
    {
        // TODO: there were like 6 or 7 repeated logs when initially enabling the module in a project. how and why?
        foreach ($this->getProjectsWithModuleEnabled() as $localProjectId) {
            $this->updateUserList($localProjectId);
            $this->updateProjectInfo($localProjectId);
            $this->updatePermissionsForAllUsers($localProjectId);
            $this->updateAllRoles($localProjectId);
            $this->updateAllDAGs($localProjectId);
        }
        $this->updateAllSystem();
        return "The \"{$cronInfo['cron_name']}\" cron job completed successfully.";
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
            $roles_to_return = count($roles) > 0 ? base64_encode(gzdeflate(json_encode($roles), 9)) : null;
            return $roles_to_return;
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
        $roles_gzip = $result->fetch_assoc()["roles"];
        return $roles_gzip;
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
            $dags_to_return = count($dags) > 0 ? base64_encode(gzdeflate(json_encode($dags), 9)) : null;
            return $dags_to_return;
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
        $dags_gzip = $result->fetch_assoc()["dags"];
        return $dags_gzip;
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
        $system_gzip = $result->fetch_assoc()["info"];
        return $system_gzip;
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

    // TODO: clean up these repetitive methods

    function getUsersByTimestamp($timestamp_clean)
    {
        $sql = "select users where message = 'users'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $users_json = $result->fetch_assoc()["users"];
        return json_decode($users_json, true);
    }

    function getProjectStatusByTimestamp($timestamp_clean)
    {
        $sql = "select info where message = 'project_info'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $info_gzip = $result->fetch_assoc()["info"];
        return json_decode(gzinflate(base64_decode($info_gzip)), true);
    }

    function getAllRolesByTimestamp($timestamp_clean)
    {
        $sql = "select roles where message = 'roles'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $roles_gzip = $result->fetch_assoc()["roles"];
        return json_decode(gzinflate(base64_decode($roles_gzip)), true);
    }

    function getAllDAGsByTimestamp($timestamp_clean)
    {
        $sql = "select dags where message = 'dags'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $dags_gzip = $result->fetch_assoc()["dags"];
        return json_decode(gzinflate(base64_decode($dags_gzip)), true);
    }

    function getAllSystemByTimestamp($timestamp_clean)
    {
        $sql = "select info where message = 'system' and project_id is null";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $system_gzip = $result->fetch_assoc()["info"];
        var_dump($system_gzip);
        return json_decode(gzinflate(base64_decode($system_gzip)), true);
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
        return $results;
    }



    /////////////////////
    // Display Methods //
    /////////////////////

    function renderTable(array $permissions)
    {
        $Renderer = new Renderer($permissions);
        try {
            $Renderer->print();
            $Renderer->renderTable();
        } catch (\Exception $e) {
            var_dump($e);
        }
    }
}
