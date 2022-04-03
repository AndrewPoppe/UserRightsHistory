<?php

namespace YaleREDCap\UserRightsHistory;

use Exception;
use ExternalModules\AbstractExternalModule;

class UserRightsHistory extends AbstractExternalModule
{
    function redcap_every_page_before_render()
    {
        $userChanges = $this->updateUserList();
        $this->updateProjectInfo();
        $this->updatePermissionsForAllUsers();
    }

    ////////////////////////////
    // PROJECT STATUS METHODS //
    ////////////////////////////

    function updateProjectInfo()
    {
        $currentProjectInfo = $this->getCurrentProjectInfo();
        $lastProjectInfo = $this->getLastProjectInfo();
        if ($lastProjectInfo == null || $this->projectInfoChanged($lastProjectInfo, $currentProjectInfo)) {
            $this->saveProjectInfo($currentProjectInfo);
        }
    }

    function getCurrentProjectInfo()
    {
        $status = $this->getProjectStatus();
        if ($status === "AC") {
            $locked = $this->getLockedStatus();
        }
        return [
            "status" => $status,
            "locked" => $locked
        ];
    }

    function getLockedStatus()
    {
        $sql = "select data_locked from redcap_projects where project_id = ?";
        $result = $this->query($sql, [$this->getProjectId()]);
        return $result->fetch_assoc()["data_locked"] == 1;
    }

    function getLastProjectInfo()
    {
        $sql = "select info where message = 'project_info' order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, []);
        $info_gzip = $result->fetch_assoc()["info"];
        return json_decode(gzinflate(base64_decode($info_gzip)), true);
    }

    function projectInfoChanged(array $oldProjectInfo, array $newProjectInfo)
    {
        $difference = array_diff_assoc($oldProjectInfo, $newProjectInfo);
        return count($difference) > 0;
    }

    function saveProjectInfo(array $newProjectInfo)
    {
        $this->log('project_info', [
            "info" => base64_encode(gzdeflate(json_encode($newProjectInfo), 9))
        ]);
    }

    ///////////////////////
    // USER LIST METHODS //
    ///////////////////////

    function updateUserList()
    {
        $currentUsers = \REDCap::getUsers();
        $lastUsers = $this->getLastUsers();
        if ($lastUsers == null) {
            $this->saveUsers($currentUsers);
            return null;
        }
        $userChanges = $this->usersChanged($lastUsers, $currentUsers);
        if ($userChanges["wereChanged"]) {
            $this->saveUsers($currentUsers);
        }
        if (count($userChanges["removed"]) > 0) {
            $this->markUsersRemoved($userChanges["removed"]);
        }
    }

    function getLastUsers()
    {
        $sql = "select users where message = 'users' order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, []);
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

    function saveUsers($users)
    {
        $this->log('users', [
            "users" => json_encode($users)
        ]);
    }

    /////////////////////////
    // USER RIGHTS METHODS //
    /////////////////////////

    function updatePermissionsForAllUsers()
    {
        try {
            $project = $this->getProject();
            $users = $project->getUsers();
            foreach ($users as $user) {
                $this->updatePermissionsForUser($user);
            }
        } catch (Exception $e) {
            $this->log("Error updating users",  ["error" => $e->getMessage()]);
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
    function updatePermissionsForUser($user)
    {
        $username = $user->getUsername();
        $currentPermissions = $this->getCurrentUserPermissions($user);
        $lastPermissions = $this->getLastUserPermissions($username);
        if ($lastPermissions == null || $this->permissionsChanged($lastPermissions, $currentPermissions)) {
            $this->savePermissions($currentPermissions);
        }
    }

    /**
     * @param user $user user object from EM framework
     * 
     * @return array permissions, including name and account status
     */
    function getCurrentUserPermissions($user)
    {

        $username = $user->getUsername();
        $name = $this->getName($username);
        $email = $user->getEmail();
        $suspended = $this->getStatus($username);
        $rights = $user->getRights();
        $isSuperUser = $user->isSuperUser();

        $rights["name"] = $name;
        $rights["email"] = $email;
        $rights["suspended"] = $suspended;
        $rights["isSuperUser"] = $isSuperUser;

        return $rights;
    }

    function getLastUserPermissions(string $username)
    {

        $sql = "select rights where message = 'rights' and user_name = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [$username]);
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

    function savePermissions(array $newPermissions)
    {
        $this->log('rights', [
            "user_name" => $newPermissions["username"],
            "rights" => base64_encode(gzdeflate(json_encode($newPermissions), 9))
        ]);
    }

    function markUsersRemoved($removed_users)
    {
        foreach ($removed_users as $username) {
            $this->log('rights', [
                "user_name" => $username,
                "rights" => null,
                "status" => "removed"
            ]);
        }
    }
}
