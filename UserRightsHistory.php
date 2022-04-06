<?php

namespace YaleREDCap\UserRightsHistory;

use Exception;
use ExternalModules\AbstractExternalModule;

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
        foreach ($this->getProjectsWithModuleEnabled() as $localProjectId) {
            $userChanges = $this->updateUserList($localProjectId);
            $this->updateProjectInfo($localProjectId);
            $this->updatePermissionsForAllUsers($localProjectId);
        }
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
        $status = $this->getProjectStatus($localProjectId);
        if ($status === "AC") {
            $locked = $this->getLockedStatus($localProjectId);
        }
        return [
            "status" => $status,
            "locked" => $locked
        ];
    }

    function getLockedStatus($localProjectId)
    {
        $sql = "select data_locked from redcap_projects where project_id = ?";
        $result = $this->query($sql, [$localProjectId]);
        return $result->fetch_assoc()["data_locked"] == 1;
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
        $difference = array_diff_assoc($oldProjectInfo, $newProjectInfo);
        return count($difference) > 0;
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
        } catch (Exception $e) {
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

        $rights["name"] = $name;
        $rights["email"] = $email;
        $rights["suspended"] = $suspended;
        $rights["isSuperUser"] = $isSuperUser;

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
        } catch (Exception $e) {
            $this->log("Error fetching name", ["error" => $e->getMessage()]);
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
        } catch (Exception $e) {
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

    function getProjectStatusByTimestamp($timestamp_clean)
    {
        $sql = "select info where message = 'project_info'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$timestamp_clean]);
        $info_gzip = $result->fetch_assoc()["info"];
        return json_decode(gzinflate(base64_decode($info_gzip)), true);
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
        $results["project_status"] = $this->getProjectStatusByTimestamp($results["timestamp"]);
        return $results;
    }

    /////////////////////
    // Display Methods //
    /////////////////////

    function renderTable(array $permissions)
    {
    }
}
