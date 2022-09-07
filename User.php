<?php

namespace YaleREDCap\UserRightsHistory;

class User
{
    function __construct(UserRightsHistory &$module, Project &$project, string $username)
    {
        $this->module = $module;
        $this->project = $project;
        $this->username = $username;
        $this->timestamp = $project->timestamp;
        $this->info = $this->getInfo();
    }

    function getInfo(): ?array
    {
        $SQL = "SELECT * FROM redcap_user_information WHERE username = ?";
        try {
            $result = $this->module->query($SQL, [$this->username]);
            return $result->fetch_assoc();
        } catch (\Exception $e) {
            $this->module->log('Error fetching user info', [
                "username" => $this->username,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }

    function getName(): ?string
    {
        return empty($this->info) ? "[user deleted]" : $this->info["user_firstname"] . " " . $this->info["user_lastname"];
    }

    function getEmail(): ?string
    {
        return empty($this->info) ? "[user deleted]" : $this->info["user_email"];
    }

    function getExpiration(): ?string
    {
        $SQL = "SELECT sql_log FROM " . $this->project->log_event_table .
            " WHERE log_event_id IN (" .
            " SELECT max(log_event_id) log_event_id FROM " . $this->project->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_user_rights'" .
            " AND description = 'Edit user expiration'" .
            " AND pk = ?)";
        try {
            $result = $this->module->query($SQL, [$this->project->pid, $this->timestamp, $this->username]);
            $sql_log = $result->fetch_assoc()["sql_log"];
            preg_match("/expiration = (.*)\n/", $sql_log, $matches);
            $expiration = trim(str_replace("'", "", $matches[1]));
            return $expiration == 'NULL' ? "" : $expiration;
        } catch (\Exception $e) {
            $this->module->log('Error fetching user expiration', [
                "username" => $this->username,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }

    function getAssignedDag(): ?array
    {
        $SQL = "SELECT sql_log FROM " . $this->project->log_event_table .
            " WHERE log_event_id IN (" .
            " SELECT max(log_event_id) log_event_id FROM " . $this->project->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_user_rights'" .
            " AND description in ('Assign user to data access group', 'Remove user from data access group')" .
            " AND pk = ?)" .
            " AND description = 'Assign user to data access group'";
        try {
            $result = $this->module->query($SQL, [$this->project->pid, $this->timestamp, $this->username]);
            $sql_log = $result->fetch_assoc()["sql_log"];
            preg_match("/group_id = (.*) where/", $sql_log, $matches);
            $this_dag = str_replace("'", "", $matches[1]);

            foreach ($this->project->dags as $dag) {
                if ($dag["dag"] == $this_dag) return $dag;
            }
            return null;
        } catch (\Exception $e) {
            $this->module->log('Error fetching user assigned dag', [
                "username" => $this->username,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }

    function getPossibleDags(): ?array
    {
        $data_value_parameter = "user = '" . $this->username . "',\n%";
        $SQL = "SELECT sql_log FROM " . $this->project->log_event_table .
            " WHERE log_event_id IN (" .
            " SELECT max(log_event_id) log_event_id FROM " . $this->project->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_data_access_groups_users'" .
            " AND data_values like ?" .
            " GROUP BY data_values)" .
            " AND description = 'DAG Switcher: Assign user to additional DAGs'";
        try {
            $result = $this->module->query($SQL, [$this->project->pid, $this->timestamp, $data_value_parameter]);
            $possible_dags = [];
            while ($row = $result->fetch_assoc()) {
                $sql_log = $row["sql_log"];
                preg_match("/values \(\'" . $this->project->pid . "\', \'(.*)\',/", $sql_log, $matches);
                $this_dag =  str_replace("'", "", $matches[1]);
                foreach ($this->project->dags as $dag) {
                    if ($dag["dag"] == $this_dag) {
                        $possible_dags[] = $dag;
                        continue;
                    }
                }
            }
            return $possible_dags;
        } catch (\Exception $e) {
            $this->module->log('Error fetching possible dags', [
                "username" => $this->username,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }

    function getRights(): ?array
    {
        $SQL = "SELECT sql_log FROM " . $this->project->log_event_table .
            " WHERE log_event_id IN (" .
            " SELECT max(log_event_id) log_event_id FROM " . $this->project->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_user_rights'" .
            " AND description IN ('Add user', 'Edit user')" .
            " AND pk = ?)";
        try {
            $result = $this->module->query($SQL, [$this->project->pid, $this->timestamp, $this->username]);
            $sql_log = $result->fetch_assoc()["sql_log"];
            $sql_log = str_replace("\n", " ", $sql_log);

            preg_match("/SET (.*) WHERE username/", $sql_log, $matches);

            // don't split array of instrument-permission mappings
            // for data entry and data export rights
            $permission_string = preg_replace_callback(
                '/(\[[a-z_0-9]+)(,)([0-9]+\])/',
                function ($new_matches) {
                    return $new_matches[1] . ';' . $new_matches[3];
                },
                $matches[1]
            );

            $permissions_raw = explode(',', $permission_string);
            $permissions = [];
            foreach ($permissions_raw as $permission_raw) {
                $pr = trim($permission_raw);
                $prs = explode('=', $pr, 2);
                $title = trim($prs[0]);
                $value = trim($prs[1], " \t\n\r\0\x0B'");
                if ($title === "data_entry" || $title === "data_export_instruments") {
                    $value = str_replace(";", ",", $value);
                }
                $permissions[$title] = $value;
            }
            return $permissions;
        } catch (\Exception $e) {
            $this->module->log('Error fetching possible dag', [
                "username" => $this->username,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }
}
