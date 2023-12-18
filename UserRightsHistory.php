<?php

namespace YaleREDCap\UserRightsHistory;

use ExternalModules\AbstractExternalModule;

include_once "User.php";
include_once "Renderer.php";
include_once "UI.php";
include_once "CsvCreator.php";

class UserRightsHistory extends AbstractExternalModule
{
    //////////////////
    // REDCap Hooks //
    //////////////////

    function redcap_module_project_enable($version, $project_id)
    {
        $this->log('module project status', [
            "version"    => $version,
            "status"     => 1,
            "project_id" => $project_id,
            "previous"   => json_encode("Disabled"),
            "current"    => json_encode("Enabled")
        ]);
    }

    function redcap_module_project_disable($version, $project_id)
    {
        $this->log('module project status', [
            "version"    => $version,
            "status"     => 0,
            "project_id" => $project_id,
            "previous"   => json_encode("Enabled"),
            "current"    => json_encode("Disabled")
        ]);
    }

    function redcap_module_system_enable($version)
    {
        $this->log('module system status', [
            "version" => $version,
            "status"  => 1
        ]);
    }

    function redcap_module_system_disable($version)
    {
        $this->log('module system status', [
            "version" => $version,
            "status"  => 0
        ]);
    }

    function redcap_module_configuration_settings($project_id, $settings)
    {
        if ( empty($project_id) ) {
            return $settings;
        }
        try {
            // Get existing user access
            $all_users = $this->getProject($project_id)->getUsers();
            foreach ( $all_users as $user ) {
                $username        = $user->getUsername();
                $name            = $this->getName($username);
                $user_key        = $username . "_access";
                $existing_access = $this->getProjectSetting($user_key, $project_id);
                $settings[]      = [
                    "key"            => $user_key,
                    "name"           => "<strong>" . ucwords($name) . "</strong> (" . $username . ")",
                    "type"           => "checkbox",
                    "branchingLogic" => [
                        "field" => "restrict-access",
                        "value" => "1"
                    ]
                ];
            }

            return $settings;
        } catch ( \Throwable $e ) {
            $this->log("Error creating configuration", [ "error" => $e->getMessage() ]);
        }
    }

    function redcap_module_link_check_display($project_id, $link)
    {
        if ( $this->getProjectSetting("restrict-access") != 1 ) {
            return $link;
        }
        $user            = $this->getUser();
        $user_access_key = $user->getUsername() . "_access";
        $access          = $this->getProjectSetting($user_access_key);
        if ( $access != "1" && $user->isSuperUser() != true ) {
            return null;
        }
        return $link;
    }

    function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
    {
        if ( $action == "logging_table_ajax" ) {
            // Is the logging page enabled?
            if ( $this->getProjectSetting("disable-logging-table") == "1" ) {
                return;
            }

            // If user is in a DAG and settings are appropriate, don't continue.
            $current_dag                   = $this->getCurrentDag($project_id, $user_id);
            $prevent_dags_from_seeing_logs = $this->getProjectSetting("prevent_logs_for_dags");
            if ( $current_dag != null && $prevent_dags_from_seeing_logs != "1" ) {
                return;
            }

            // Return logging data
            [ $logs, $recordsFiltered ] = $this->getLogs($payload);
            $total                      = $this->getTotalLogCount();

            $response = array(
                "data"            => $logs,
                "draw"            => (int) $payload["draw"],
                "recordsTotal"    => $total,
                "recordsFiltered" => $recordsFiltered
            );
            return $response;
        } else if ( $action == "history_viewer_csv" ) {
            try {
                $timestamp   = $payload["timestamp"];
                $permissions = $this->getAllInfoByTimestamp($timestamp);
                $csvCreator  = new CsvCreator($permissions);
                switch ($payload["type"]) {
                    case "users":
                        return $csvCreator->createUsersArray();
                    case "roles":
                        return $csvCreator->createRolesArray();
                    case "role_assignments":
                        return $csvCreator->createRoleAssignmentsArray();
                }
            } catch ( \Throwable $e ) {
                $this->log("Error creating csv", [ "error" => $e->getMessage() ]);
            }
        }
    }

    //////////////////////
    // Settings Methods //
    //////////////////////

    function shouldDagsBeChecked()
    {
        $dags_setting = $this->getProjectSetting("restrict-dag");
        $current_dag  = $this->getCurrentDag($this->getProject()->getProjectId(), $this->getUser()->getUsername());
        $superUser    = $this->getUser()->isSuperUser();
        return !$superUser && $dags_setting != "1" && !is_null($current_dag);
    }

    function isDagRestricted($dag)
    {
        $current_dag = $this->getCurrentDag($this->getProject()->getProjectId(), $this->getUser()->getUsername());
        return $this->shouldDagsBeChecked() && $dag != $current_dag;
    }


    //////////////////
    // Main Methods //
    //////////////////

    function updateEnabledByDefaultStatus($currentStatus)
    {
        $lastStatusResult = $this->queryLogs("select status where message = ? order by timestamp desc limit 1", [ 'module enabled by default status' ]);
        $lastStatus       = $lastStatusResult->fetch_assoc()["status"];
        if ( $currentStatus != $lastStatus ) {
            $this->log('module enabled by default status', [ 'status' => $currentStatus ]);
        }
    }

    function getAllProjectIds()
    {
        try {
            $query       = "select project_id from redcap_projects
            where created_by is not null
            and completed_time is null
            and date_deleted is null";
            $result      = $this->query($query, []);
            $project_ids = [];
            while ( $row = $result->fetch_assoc() ) {
                $project_ids[] = $row["project_id"];
            }
            return $project_ids;
        } catch ( \Throwable $e ) {
            $this->log("Error fetching all projects", [ "error" => $e->getMessage() ]);
        }
    }

    function logVersionIfNeeded()
    {
        $current_version = end(explode("_", $this->getModuleDirectoryName()));
        $sql             = "select version where message = 'module version' and version = ? order by timestamp desc";
        $result          = $this->queryLogs($sql, [ $current_version ]);
        $row             = $result->fetch_assoc();
        if ( empty($row) ) {
            $this->log('module version', [ 'version' => $current_version, 'current' => json_encode($current_version) ]);
        }
    }

    function updateProjectStatusMessageIfNeeded($localProjectId)
    {
        $sql    = "select timestamp where message = 'module project status' and project_id = ?";
        $result = $this->queryLogs($sql, [ $localProjectId ]);
        $row    = $result->fetch_assoc();
        if ( empty($row) && in_array($localProjectId, $this->getProjectsWithModuleEnabled()) ) {
            $this->log('module project status', [
                "project_id" => $localProjectId,
                "status"     => 1,
                "current"    => json_encode("Enabled")
            ]);
        }
    }

    function updateAllProjects($cronInfo = array())
    {
        try {
            $enabledSystemwide = $this->getSystemSetting('enabled');
            $this->updateEnabledByDefaultStatus($enabledSystemwide);

            // log new versions manually
            $this->logVersionIfNeeded();

            if ( $enabledSystemwide == true ) {
                $all_project_ids = $this->getAllProjectIds();
                $project_ids     = array_filter($all_project_ids, function ($project_id) {
                    return $this->isModuleEnabled('user_rights_history', $project_id);
                });
            } else {
                $project_ids = $this->getProjectsWithModuleEnabled();
            }

            foreach ( $project_ids as $localProjectId ) {
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
        } catch ( \Throwable $e ) {
            $this->log("Error updating projects", [ "error" => $e->getMessage() ]);
            return "The \"{$cronInfo['cron_name']}\" cron job failed: " . $e->getMessage();
        }
    }

    ////////////////////////////
    // PROJECT STATUS METHODS //
    ////////////////////////////

    function updateProjectInfo($localProjectId)
    {
        $currentProjectInfo = $this->getCurrentProjectInfo($localProjectId) ?? [];
        $lastProjectInfo    = $this->getLastProjectInfo($localProjectId) ?? [];
        $changes            = $this->projectInfoChanges($lastProjectInfo, $currentProjectInfo);
        if ( is_null($lastProjectInfo) || $changes["any_changes"] ) {
            $this->saveProjectInfo($localProjectId, $currentProjectInfo, $changes);
        }
    }

    function getCurrentProjectInfo($localProjectId)
    {
        try {
            $sql          = "select * from redcap_projects where project_id = ?";
            $result       = $this->query($sql, [ $localProjectId ]);
            $result_array = $result->fetch_assoc();
            unset($result_array["last_logged_event"]); // Prevent unncessary updates
            return $result_array;
        } catch ( \Throwable $e ) {
            $this->log('Error fetching project info', [ 'error' => $e->getMessage() ]);
        }
    }

    function getLastProjectInfo($localProjectId)
    {
        $sql       = "select info where message = 'project_info' and project_id = ? order by timestamp desc limit 1";
        $result    = $this->queryLogs($sql, [ $localProjectId ]);
        $info_gzip = $result->fetch_assoc()["info"];
        return json_decode(gzinflate(base64_decode($info_gzip)), true);
    }

    function projectInfoChanges(?array $oldProjectInfo, ?array $newProjectInfo)
    {
        $oldProjectInfo = is_null($oldProjectInfo) ? [] : $oldProjectInfo;
        $newProjectInfo = is_null($newProjectInfo) ? [] : $newProjectInfo;
        $difference1    = array_diff_assoc($oldProjectInfo, $newProjectInfo);
        $difference2    = array_diff_assoc($newProjectInfo, $oldProjectInfo);
        $any_changes    = (count($difference1) + count($difference2)) > 0;
        return [
            "previous"    => $difference1,
            "current"     => $difference2,
            "any_changes" => $any_changes
        ];
    }

    function saveProjectInfo($localProjectId, array $newProjectInfo, array $changes)
    {
        $this->log('project_info', [
            "project_id" => $localProjectId,
            "info"       => base64_encode(gzdeflate(json_encode($newProjectInfo), 9)),
            "previous"   => json_encode($changes["previous"]),
            "current"    => json_encode($changes["current"]),
            "event"      => sizeof($changes["previous"]) === 0 ? "Initialize URH Module" : "Update Project"
        ]);
    }

    ///////////////////////
    // USER LIST METHODS //
    ///////////////////////

    function getCurrentUsers($localProjectId)
    {
        $project = $this->getProject($localProjectId);
        $users   = $project->getUsers();
        $result  = array();
        foreach ( $users as $user ) {
            $result[] = $user->getUsername();
        }
        return $result;
    }

    function updateUserList($localProjectId)
    {
        $currentUsers = $this->getCurrentUsers($localProjectId);
        $lastUsers    = $this->getLastUsers($localProjectId);
        // if ($lastUsers == null) {
        //     $changes = array("added" => $currentUsers, "removed" => null);
        //     $this->saveUsers($localProjectId, $currentUsers, $changes);
        //     return null;
        // }
        $userChanges = $this->usersChanged($lastUsers, $currentUsers);
        if ( $userChanges["wereChanged"] ) {
            $this->saveUsers($localProjectId, $currentUsers, $userChanges);
        }
        if ( count($userChanges["removed"]) > 0 ) {
            $this->markUsersRemoved($localProjectId, $userChanges["removed"]);
        }
    }

    function getLastUsers($localProjectId)
    {
        $sql        = "select users where message = 'users' and project_id = ? order by timestamp desc limit 1";
        $result     = $this->queryLogs($sql, [ $localProjectId ]);
        $users_json = $result->fetch_assoc()["users"];
        return json_decode($users_json, true);
    }

    function usersChanged($oldUsers, $newUsers)
    {
        $result                = array();
        $oldUsers              = is_null($oldUsers) ? [] : $oldUsers;
        $newUsers              = is_null($newUsers) ? [] : $newUsers;
        $result["removed"]     = array_diff($oldUsers, $newUsers);
        $result["added"]       = array_diff($newUsers, $oldUsers);
        $result["wereChanged"] = count($result["removed"]) > 0 || count($result["added"]) > 0;
        $result["previous"]    = $oldUsers;
        $result["current"]     = $newUsers;
        return $result;
    }

    function saveUsers($localProjectId, $users, ?array $changes)
    {
        $event = "";
        if ( sizeof($changes["added"]) > 0 ) {
            $event .= "Add User(s)";
        }
        if ( sizeof($changes["removed"]) > 0 ) {
            $event .= (sizeof($changes["added"]) > 0) ? "and Remove User(s)" : "Remove User(s)";
        }
        $this->log('users', [
            "project_id" => $localProjectId,
            "users"      => json_encode($users),
            "added"      => json_encode($changes["added"]),
            "removed"    => json_encode($changes["removed"]),
            "previous"   => json_encode($changes["previous"]),
            "current"    => json_encode($changes["current"]),
            "event"      => $event
        ]);
    }

    ///////////////////
    // ROLES METHODS //
    ///////////////////

    function updateAllRoles($localProjectId)
    {
        $currentRolesGzip = $this->getCurrentRoles($localProjectId);
        $lastRolesGzip    = $this->getLastRoles($localProjectId);
        $rolesChanges     = $this->getRolesChanges($lastRolesGzip, $currentRolesGzip);
        if ( $rolesChanges["any_changes"] ) {
            $this->saveRoles($localProjectId, $currentRolesGzip, $rolesChanges);
        }
    }

    function getCurrentRoles($localProjectId)
    {
        try {
            $sql    = "select * from redcap_user_roles where project_id = ?";
            $result = $this->query($sql, [ $localProjectId ]);
            $roles  = array();
            while ( $role = $result->fetch_assoc() ) {
                $roles[$role["role_id"]] = $role;
            }
            return !empty($roles) ? base64_encode(gzdeflate(json_encode($roles), 9)) : null;
        } catch ( \Throwable $e ) {
            $this->log("Error updating roles", [
                "project_id" => $localProjectId,
                "error"      => $e->getMessage()
            ]);
        }
    }

    function getLastRoles($localProjectId)
    {
        $sql    = "select roles where message = 'roles' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [ $localProjectId ]);
        return $result->fetch_assoc()["roles"];
    }

    function getRolesChanges($lastRolesGzip, $currentRolesGzip)
    {
        $changes = array( "any_changes" => false );
        if ( $lastRolesGzip !== $currentRolesGzip ) {
            $lastRoles           = json_decode(gzinflate(base64_decode($lastRolesGzip)), true) ?? [];
            $currentRoles        = json_decode(gzinflate(base64_decode($currentRolesGzip)), true) ?? [];
            $changes["previous"] = array();
            foreach ( $lastRoles as $index => $lastRole ) {
                if ( empty($currentRoles[$index]) ) {
                    $changes["previous"][$index] = $lastRole;
                } else {
                    $changes["previous"][$index] = array_diff_assoc($lastRole, $currentRoles[$index]);
                }
            }
            $changes["current"] = array();
            foreach ( $currentRoles as $index => $currentRole ) {
                if ( empty($lastRoles[$index]) ) {
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
            "roles"      => $rolesGzip,
            "previous"   => json_encode($changes["previous"]),
            "current"    => json_encode($changes["current"])
        ]);
    }



    /////////////////////////
    // USER RIGHTS METHODS //
    /////////////////////////

    function updatePermissionsForAllUsers($localProjectId)
    {
        try {
            $project = $this->getProject($localProjectId);
            $users   = $project->getUsers();
            foreach ( $users as $user ) {
                $this->updatePermissionsForUser($localProjectId, $user);
            }
        } catch ( \Throwable $e ) {
            $this->log("Error updating users", [
                "project_id" => $localProjectId,
                "error"      => $e->getMessage()
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
        $username                = $user->getUsername();
        $currentPermissions_gzip = $this->getCurrentUserPermissions($localProjectId, $user);
        $lastPermissions_gzip    = $this->getLastUserPermissions($localProjectId, $username);
        $permissionsChanges      = $this->getPermissionsChanges($lastPermissions_gzip, $currentPermissions_gzip, $username);
        if ( $lastPermissions_gzip == null || $permissionsChanges["any_changes"] ) {
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

        $username     = $user->getUsername();
        $name         = $this->getName($username);
        $email        = $user->getEmail();
        $suspended    = $this->getStatus($username);
        $rights       = $user->getRights($localProjectId);
        $isSuperUser  = $user->isSuperUser();
        $possibleDags = $this->getPossibleDags($localProjectId, $username);

        $rights["name"]         = $name;
        $rights["email"]        = $email;
        $rights["suspended"]    = $suspended;
        $rights["isSuperUser"]  = $isSuperUser;
        $rights["possibleDags"] = $possibleDags;

        return base64_encode(gzdeflate(json_encode($rights), 9));
    }

    function getLastUserPermissions($localProjectId, string $username)
    {

        $sql         = "select rights where message = 'rights' and user_name = ? and project_id = ? order by timestamp desc limit 1";
        $result      = $this->queryLogs($sql, [ $username, $localProjectId ]);
        $rights_gzip = $result->fetch_assoc()["rights"];
        return $rights_gzip;
    }

    function getName(string $username)
    {
        try {
            $sql    = "select concat(user_firstname, ' ', user_lastname) name
            from redcap_user_information
            where username = ?";
            $result = $this->query($sql, [ $username ]);
            return $result->fetch_assoc()["name"];
        } catch ( \Throwable $e ) {
            $this->log("Error fetching name", [ "error" => $e->getMessage() ]);
        }
    }

    function getCurrentDag($localProjectId, $username)
    {
        try {
            $sql    = "select group_id from redcap_user_rights where project_id = ? and username = ?";
            $result = $this->query($sql, [ $localProjectId, $username ]);
            $dag    = $result->fetch_assoc()["group_id"];
            return $dag;
        } catch ( \Throwable $e ) {
            $this->log("Error fetching current dag", [ "error" => $e->getMessage() ]);
        }
    }

    function getPossibleDags($localProjectId, string $username)
    {
        try {
            $sql          = "select group_id from redcap_data_access_groups_users where project_id = ? and username = ?";
            $result       = $this->query($sql, [ $localProjectId, $username ]);
            $possibleDags = array();
            while ( $row = $result->fetch_assoc() ) {
                $possibleDags[] = $row["group_id"];
            }
            return $possibleDags;
        } catch ( \Throwable $e ) {
            $this->log("Error fetching possible dags", [ "error" => $e->getMessage() ]);
        }
    }

    function getStatus(string $username)
    {
        try {
            $sql    = "select user_suspended_time < NOW() suspended
            from redcap_user_information
            where username = ?";
            $result = $this->query($sql, [ $username ]);
            return $result->fetch_assoc()["suspended"] === 1;
        } catch ( \Throwable $e ) {
            $this->log("Error fetching status", [ "error" => $e->getMessage() ]);
        }
    }

    function getPermissionsChanges(?string $oldPermissions_gzip, ?string $newPermissions_gzip, $username)
    {
        try {
            $changes = array( "any_changes" => false );
            if ( $oldPermissions_gzip !== $newPermissions_gzip ) {
                $changes["any_changes"] = true;
                $oldPermissions         = json_decode(gzinflate(base64_decode($oldPermissions_gzip)), true) ?? [];
                $newPermissions         = json_decode(gzinflate(base64_decode($newPermissions_gzip)), true) ?? [];

                $changes["previous"] = array_diff_assoc($oldPermissions, $newPermissions);
                $changes["current"]  = array_diff_assoc($newPermissions, $oldPermissions);
                if ( empty($changes["previous"]["username"]) && empty($changes["current"]["username"]) ) {
                    $changes["previous"]["username"] = $username;
                    $changes["current"]["username"]  = $username;
                }

                $possibleDagChanges_previous = array_diff_assoc($oldPermissions["possibleDags"] ?? [], $newPermissions["possibleDags"]);
                $possibleDagChanges_current  = array_diff_assoc($newPermissions["possibleDags"], $oldPermissions["possibleDags"] ?? []);

                if ( count($possibleDagChanges_previous) > 0 || count($possibleDagChanges_current) > 0 ) {
                    $changes["previous"]["possibleDags"] = $possibleDagChanges_previous;
                    $changes["current"]["possibleDags"]  = $possibleDagChanges_current;
                }
            }
            return $changes;
        } catch ( \Throwable $e ) {
            $this->log('Error getting user permission changes', [ "username" => $username, "error" => $e->getMessage() ]);
        }
    }

    function savePermissions($localProjectId, string $newPermissions_gzip, array $permissionsChanges, $username)
    {
        try {
            $previous = $permissionsChanges["previous"];
            if ( !empty($previous['api_token']) ) {
                $previous['api_token'] = $this->replaceMiddleChars($previous['api_token']);
            }
            $current = $permissionsChanges["current"];
            if ( !empty($current['api_token']) ) {
                $current['api_token'] = $this->replaceMiddleChars($current['api_token']);
            }
            $this->log('rights', [
                "user_name"  => $username,
                "project_id" => $localProjectId,
                "rights"     => $newPermissions_gzip,
                "previous"   => json_encode($previous),
                "current"    => json_encode($current)
            ]);
        } catch ( \Throwable $e ) {
            $this->log('Error saving user permissions', [ "username" => $username, "error" => $e->getMessage() ]);
        }
    }

    function markUsersRemoved($localProjectId, $removed_users)
    {
        foreach ( $removed_users as $username ) {
            try {
                $lastPermissions_gzip = $this->getLastUserPermissions($localProjectId, $username);
                $changes              = $this->getPermissionsChanges($lastPermissions_gzip, null, $username);
                $this->log('rights', [
                    "user_name"  => $username,
                    "rights"     => null,
                    "status"     => "removed",
                    "project_id" => $localProjectId,
                    "previous"   => json_encode($changes["previous"]),
                    "current"    => json_encode($changes["current"])
                ]);
            } catch ( \Throwable $e ) {
                $this->log('Error marking user removed', [ "username" => $username, "error" => $e->getMessage() ]);
            }
        }
    }


    /////////////////
    // DAG Methods //
    /////////////////


    function updateAllDAGs($localProjectId)
    {
        $currentDAGsGzip = $this->getCurrentDAGs($localProjectId);
        $lastDAGsGzip    = $this->getLastDAGs($localProjectId);
        $dagChanges      = $this->getDAGsChanges($lastDAGsGzip, $currentDAGsGzip);
        if ( $dagChanges["any_changes"] ) {
            $this->saveDAGs($localProjectId, $currentDAGsGzip, $dagChanges);
        }
    }

    function getCurrentDAGs($localProjectId)
    {
        try {
            $sql    = "select * from redcap_data_access_groups where project_id = ?";
            $result = $this->query($sql, [ $localProjectId ]);
            $dags   = array();
            while ( $dag = $result->fetch_assoc() ) {
                $dags[$dag["group_id"]] = $dag;
            }
            return !empty($dags) ? base64_encode(gzdeflate(json_encode($dags), 9)) : null;
        } catch ( \Throwable $e ) {
            $this->log("Error updating dags", [
                "project_id" => $localProjectId,
                "error"      => $e->getMessage()
            ]);
        }
    }

    function getLastDAGs($localProjectId)
    {
        $sql    = "select dags where message = 'dags' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [ $localProjectId ]);
        return $result->fetch_assoc()["dags"];
    }

    function getDAGsChanges($lastDAGsGzip, $currentDAGsGzip)
    {
        $changes = array( "any_changes" => false );
        if ( $lastDAGsGzip !== $currentDAGsGzip ) {
            $changes["any_changes"] = true;
            $lastDAGs               = json_decode(gzinflate(base64_decode($lastDAGsGzip)), true) ?? [];
            $currentDAGs            = json_decode(gzinflate(base64_decode($currentDAGsGzip)), true) ?? [];
            $changes["previous"]    = array();
            foreach ( $lastDAGs as $index => $lastDAG ) {
                if ( empty($currentDAGs[$index]) ) {
                    $changes["previous"][$index] = $lastDAG;
                } else {
                    $changes["previous"][$index] = array_diff_assoc($lastDAG, $currentDAGs[$index]);
                }
            }
            $changes["current"] = array();
            foreach ( $currentDAGs as $index => $currentDAG ) {
                if ( empty($lastDAGs[$index]) ) {
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
            "dags"       => $dagsGzip,
            "current"    => json_encode($dagChanges["current"]),
            "previous"   => json_encode($dagChanges["previous"])
        ]);
    }

    ////////////////////
    // System Methods //
    ////////////////////

    function updateAllSystem()
    {
        try {
            $currentSystemGzip = $this->getCurrentSystem();
            $lastSystemGzip    = $this->getLastSystem();
            $systemChanges     = $this->getSystemChanges($lastSystemGzip, $currentSystemGzip);
            if ( empty($lastSystemGzip) || $systemChanges["any_changes"] ) {
                $this->saveSystem($currentSystemGzip, $systemChanges);
            }
        } catch ( \Throwable $e ) {
            $this->log("Error updating system", [
                "error" => $e->getMessage()
            ]);
        }
    }

    function getCurrentSystem()
    {
        try {
            $sql         = "select * from redcap_config";
            $result      = $this->query($sql, []);
            $fields      = [ "enable_plotting", "dts_enabled_global", "api_enabled", "mobile_app_enabled", "mycap_enabled_global" ];
            $system_info = array();
            while ( $row = $result->fetch_assoc() ) {
                if ( in_array($row["field_name"], $fields, true) ) {
                    $system_info[$row["field_name"]] = $row["value"];
                }
            }
            return base64_encode(gzdeflate(json_encode($system_info), 9));
        } catch ( \Throwable $e ) {
            $this->log("Error getting current system", [
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastSystem()
    {
        $sql    = "select info where message = 'system' order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, []);
        return $result->fetch_assoc()["info"];
    }

    function getSystemChanges($lastSystemGzip, $currentSystemGzip)
    {
        $changes = array( "any_changes" => false );
        if ( $lastSystemGzip !== $currentSystemGzip ) {
            $changes["any_changes"] = true;
            $lastSystem             = json_decode(gzinflate(base64_decode($lastSystemGzip)), true) ?? [];
            $currentSystem          = json_decode(gzinflate(base64_decode($currentSystemGzip)), true) ?? [];
            $changes["previous"]    = array_diff_assoc($lastSystem, $currentSystem);
            $changes["current"]     = array_diff_assoc($currentSystem, $lastSystem);
        }
        return $changes;
    }

    function saveSystem($system_gzip, $systemChanges)
    {
        $this->log('system', [
            "info"     => $system_gzip,
            "current"  => json_encode($systemChanges["current"]),
            "previous" => json_encode($systemChanges["previous"])
        ]);
    }

    ////////////////////////
    // Instrument Methods //
    ////////////////////////

    function updateAllInstruments($localProjectId)
    {
        $currentInstrumentsGzip = $this->getCurrentInstruments($localProjectId);
        $lastInstrumentsGzip    = $this->getLastInstruments($localProjectId);
        $instrumentsChanges     = $this->getInstrumentsChanges($lastInstrumentsGzip, $currentInstrumentsGzip);
        if ( $instrumentsChanges["any_changes"] ) {
            $this->saveInstruments($localProjectId, $currentInstrumentsGzip, $instrumentsChanges);
        }
    }

    function getCurrentInstruments($localProjectId)
    {
        try {
            $sql         = "select m.form_name id,max(m.form_menu_description) title, s.survey_id from redcap_metadata m
            left join redcap_surveys s
            on m.form_name = s.form_name
            and m.project_id = s.project_id
            where m.project_id = ?
            group by m.project_id, m.form_name
            order by m.field_order";
            $result      = $this->query($sql, [ $localProjectId ]);
            $instruments = [];
            while ( $instrument = $result->fetch_assoc() ) {
                $instruments[$instrument["id"]] = $instrument;
            }
            return base64_encode(gzdeflate(json_encode($instruments), 9));
        } catch ( \Throwable $e ) {
            $this->log("Error updating instruments", [
                "error" => $e->getMessage()
            ]);
        }
    }

    function getLastInstruments($localProjectId)
    {
        $sql    = "select instruments where message = 'instruments' and project_id = ? order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [ $localProjectId ]);
        return $result->fetch_assoc()["instruments"];
    }

    function getInstrumentsChanges($lastInstrumentsGzip, $currentInstrumentsGzip)
    {
        $changes = array( "any_changes" => false );
        if ( $lastInstrumentsGzip !== $currentInstrumentsGzip ) {
            $changes["any_changes"] = true;
            $lastInstruments        = json_decode(gzinflate(base64_decode($lastInstrumentsGzip)), true) ?? [];
            $currentInstruments     = json_decode(gzinflate(base64_decode($currentInstrumentsGzip)), true) ?? [];
            $changes["previous"]    = array_diff_assoc($lastInstruments, $currentInstruments);
            $changes["current"]     = array_diff_assoc($currentInstruments, $lastInstruments);
        }
        return $changes;
    }

    function saveInstruments($localProjectId, $instruments_gzip, $instrumentsChanges)
    {
        $this->log('instruments', [
            "project_id"  => $localProjectId,
            "instruments" => $instruments_gzip,
            "previous"    => json_encode($instrumentsChanges["previous"]),
            "current"     => json_encode($instrumentsChanges["current"])
        ]);
    }

    ///////////////////////////
    // Module Status Methods //
    ///////////////////////////

    function getModuleSystemStatusByTimestamp($timestamp_clean)
    {
        $system_status_sql    = "select status where message = 'module system status' and project_id is null";
        $system_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $system_status_sql .= " order by timestamp desc limit 1";
        $system_status_result = $this->queryLogs($system_status_sql, [ $timestamp_clean ]);
        $status               = $system_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleProjectStatusByTimestamp($timestamp_clean)
    {
        $project_status_sql    = "select status where message = 'module project status'";
        $project_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $project_status_sql .= " order by timestamp desc limit 1";
        $project_status_result = $this->queryLogs($project_status_sql, [ $timestamp_clean ]);
        $status                = $project_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleDefaultEnabledByTimestamp($timestamp_clean)
    {
        $default_status_sql    = "select status where message = 'module enabled by default status' and project_id is null";
        $default_status_sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $default_status_sql .= " order by timestamp desc limit 1";
        $default_status_result = $this->queryLogs($default_status_sql, [ $timestamp_clean ]);
        $status                = $default_status_result->fetch_assoc()["status"];
        return isset($status) ? intval($status) : $status;
    }

    function getModuleStatusByTimestamp($timestamp_clean)
    {
        $system_status = $this->getModuleSystemStatusByTimestamp($timestamp_clean);
        if ( $system_status === 0 ) {
            return 0;
        }

        $project_status = $this->getModuleProjectStatusByTimestamp($timestamp_clean);
        if ( $project_status === 1 ) {
            return 1;
        } else if ( $project_status === 0 ) {
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
        $sql       = "select UNIX_TIMESTAMP(timestamp) where message = 'module version' and project_id is null order by timestamp limit 1";
        $result    = $this->queryLogs($sql, []);
        $timestamp = $result->fetch_assoc()["UNIX_TIMESTAMP(redcap_external_modules_log.timestamp)"];
        return intval($timestamp);
    }

    function isTimestampOld($timestamp_clean)
    {
        $sql    = "message = 'module version' and project_id is null";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $result = intval($this->countLogs($sql, [ $timestamp_clean ]));
        return $result === 0;
    }


    ///////////////////////
    // Filtering Methods //
    ///////////////////////

    function getUserPermissionsByTimestamp($username, $timestamp_clean)
    {
        $sql         = "select rights where message = 'rights' and user_name = ?";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result      = $this->queryLogs($sql, [ $username, $timestamp_clean ]);
        $rights_gzip = $result->fetch_assoc()["rights"];
        return json_decode(gzinflate(base64_decode($rights_gzip)), true);
    }

    function getUsersByTimestamp($timestamp_clean)
    {
        $sql        = "select users where message = 'users'";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result     = $this->queryLogs($sql, [ $timestamp_clean ]);
        $users_json = $result->fetch_assoc()["users"];
        return json_decode($users_json, true);
    }

    private function getValueByTimestamp($timestamp_clean, $message, $column, $system = false)
    {
        $sql    = "select $column where message = ?";
        $sql .= $system ? " and project_id is null" : "";
        $sql .= $timestamp_clean === 0 ? "" : " and timestamp <= from_unixtime(?)";
        $sql .= " order by timestamp desc limit 1";
        $result = $this->queryLogs($sql, [ $message, $timestamp_clean ]);
        $gzip   = $result->fetch_assoc()[$column];
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
        $results              = array();
        $results["timestamp"] = intval($timestamp) / 1000;

        $users            = $this->getUsersByTimestamp($results["timestamp"]);
        $results["users"] = array();
        foreach ( $users as $user ) {
            $userInfo = $this->getUserPermissionsByTimestamp($user, $results["timestamp"]);
            if ( !$this->isDagRestricted($userInfo["group_id"]) ) {
                $results["users"][$user] = $userInfo;
            }
        }

        $results["dags"]           = $this->getAllDAGsByTimestamp($results["timestamp"]);
        $results["roles"]          = $this->getAllRolesByTimestamp($results["timestamp"]);
        $results["project_status"] = $this->getProjectStatusByTimestamp($results["timestamp"]);
        $results["system"]         = $this->getAllSystemByTimestamp($results["timestamp"]);
        $results["instruments"]    = $this->getAllInstrumentsByTimestamp($results["timestamp"]);

        $results["module_status"] = $this->getModuleStatusByTimestamp($results["timestamp"]);
        $results["old"]           = $this->isTimestampOld($results["timestamp"]);
        return $results;
    }


    function getEarliestLogTimestamp()
    {
        $sql       = "select UNIX_TIMESTAMP(timestamp) order by timestamp limit 1";
        $result    = $this->queryLogs($sql, []);
        $timestamp = $result->fetch_assoc()["UNIX_TIMESTAMP(redcap_external_modules_log.timestamp)"];
        return $timestamp * 1000 + 60000;
    }

    ///////////////////////////
    // Logging Table Methods //
    ///////////////////////////

    function getTotalLogCount()
    {
        try {
            $queryResult = $this->queryLogs("select count(timestamp) ts where (project_id = ? or project_id is null) and message in (
                'dags',
                'instruments',
                'module enabled by default status',
                'module project status',
                'module system status',
                'module version',
                'project_info',
                'rights',
                'roles',
                'system',
                'users'
            )", [ $this->getProjectId() ]);
            return intval($queryResult->fetch_assoc()["ts"]);
        } catch ( \Throwable $e ) {
            $this->log('Error getting total log count', [ "error" => $e->getMessage() ]);
            return;
        }
    }

    function replaceMiddleChars(string $token)
    {
        $len = strlen($token);
        if ( $len < 6 ) {
            return "REDACTED";
        }
        $start  = substr($token, 0, 3);
        $end    = substr($token, $len - 3, $len);
        $middle = substr($token, 3, $len - 6);
        $middle = str_repeat('*', strlen($middle));
        return $start . $middle . $end;
    }

    function redactApiToken(array $data)
    {
        $previous_str = $data['previous'] ?? '[]';
        $current_str  = $data['current'] ?? '[]';
        $previous     = json_decode($previous_str, true);
        $current      = json_decode($current_str, true);
        if ( !empty($previous['api_token']) ) {
            $previous['api_token'] = $this->replaceMiddleChars($previous['api_token']);
        }
        if ( !empty($current['api_token']) ) {
            $current['api_token'] = $this->replaceMiddleChars($current['api_token']);
        }
        $data['previous'] = json_encode($previous);
        $data['current']  = json_encode($current);
        return $data;
    }

    function getLogs(array $params)
    {
        try {
            $start     = (int) $params["start"];
            $length    = (int) $params["length"];
            $limitTerm = ($length < 0) ? "" : " limit " . $start . "," . $length;


            $queryParameters = [ $this->getProjectId() ];

            $generalSearchTerm       = $params["search"]["value"] ?? "";
            $generalSearchText       = $generalSearchTerm === "" ? "" : " and (";
            $generalSearchParameters = [];
            $columnSearchText        = "";
            $columnSearchParameters  = [];
            foreach ( $params["columns"] as $column ) {

                // Add column to general search if it is searchable
                if ( $generalSearchTerm !== "" && $column["searchable"] == "true" ) {
                    if ( $generalSearchText !== " and (" ) {
                        $generalSearchText .= " or ";
                    }
                    $generalSearchText .= db_escape($column["data"]) . " like ?";
                    array_push($queryParameters, sprintf("%%%s%%", $generalSearchTerm));
                }

                // Add any column-specific filtering
                $searchVal = $column["search"]["value"];
                if ( $searchVal != "" ) {
                    $columnSearchText .= " and " . db_escape($column["data"]) . " like ?";
                    $searchVal        = $column["search"]["regex"] == "true" ? $searchVal : sprintf("%%%s%%", $searchVal);
                    array_push($columnSearchParameters, $searchVal);
                }
            }
            $generalSearchText .= $generalSearchText === "" ? "" : ")";

            // Timestamp filtering
            $timestampFilterText       = "";
            $timestampFilterParameters = [];
            if ( $params["minDate"] != "" ) {
                $timestampFilterText .= " and timestamp >= ?";
                $timestampFilterParameters[] = $params["minDate"];
            }
            if ( $params["maxDate"] != "" ) {
                $timestampFilterText .= " and timestamp <= ?";
                $timestampFilterParameters[] = $params["maxDate"];
            }

            $orderTerm = "";
            foreach ( $params["order"] as $index => $order ) {
                $column    = db_escape($params["columns"][intval($order["column"])]["data"]);
                $direction = $order["dir"] === "asc" ? "asc" : "desc";
                if ( $index === 0 ) {
                    $orderTerm .= " order by ";
                }
                $orderTerm .= $column . " " . $direction;
                if ( $index !== sizeof($params["order"]) - 1 ) {
                    $orderTerm .= ", ";
                }
            }
            if ( $orderTerm === "" ) {
                $orderTerm = " order by timestamp desc";
            }

            $queryParameters = [ ...$queryParameters, ...$generalSearchParameters, ...$columnSearchParameters, ...$timestampFilterParameters ];

            $queryText = "select timestamp, message, current, previous where (project_id = ? or project_id is null) and message in (
                'dags',
                'instruments',
                'module enabled by default status',
                'module project status',
                'module system status',
                'module version',
                'project_info',
                'rights',
                'roles',
                'system',
                'users'
            )" . $generalSearchText . $columnSearchText . $timestampFilterText . $orderTerm . $limitTerm;
            $countText = "select count(timestamp) ts where (project_id = ? or project_id is null) and message in (
                'dags',
                'instruments',
                'module enabled by default status',
                'module project status',
                'module system status',
                'module version',
                'project_info',
                'rights',
                'roles',
                'system',
                'users'
            )" . $generalSearchText . $columnSearchText . $timestampFilterText;

            $queryResult = $this->queryLogs($queryText, $queryParameters);
            $countResult = $this->queryLogs($countText, $queryParameters);
            $rowsTotal   = $countResult->fetch_assoc()["ts"];
            $logs        = array();
            while ( $row = $queryResult->fetch_assoc() ) {
                $logs[] = $this->redactApiToken($row);
            }
            return [ $logs, $rowsTotal ];
        } catch ( \Throwable $e ) {
            $this->log('Error getting logs', [ "error" => $e->getMessage() ]);
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
        } catch ( \Throwable $e ) {
            $this->log('Error rendering table', [ 'message' => $e->getMessage() ]);
        }
    }

    function showPageHeader(string $page)
    {
        $UI = new UI($this);
        $UI->showPageHeader($page);
    }
}
