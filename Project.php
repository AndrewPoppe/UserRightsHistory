<?php

namespace YaleREDCap\UserRightsHistory;

include_once "User.php";

class Project
{
    function __construct(UserRightsHistory $module, int $pid, ?int $timestamp)
    {
        $this->pid = $pid;
        $this->module = $module;
        $this->timestamp = $timestamp ?? date("YmdHis");
        $this->project = $module->getProject($pid);
        $this->log_event_table = $this->project->getLogTable($pid);
        $this->dags = $this->getDags();
    }

    function getStatus()
    {
        $sql = "SELECT * FROM " . $this->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND event = 'MANAGE'" .
            " AND description like 'Move project %'" .
            " OR description like 'Project marked as %'" .
            " OR description like 'Project moved from %'" .
            " OR description like 'Project data set to %'" .
            " OR description like ' %'" .
            " OR description like ' %'" .


            " 'Move project to Production status'," .                                   // Development -> Production
            " 'Move project back to Development status'," .                             // Production -> Development
            " 'Project marked as Completed'," .                                         // * -> Completed
            " 'Project moved from Completed status back to Development status'," .      // Completed -> Development
            " 'Project moved from Completed status back to Production status'," .       // Completed -> Production
            " 'Project moved from Completed status back to Production status'," .       // Completed -> Production

            " 'Move project to Analysis/Cleanup status'," .
            " 'Project data set to Read-only/Locked mode'," .
            " 'Project data set to Editable mode'," .
            " ''," .
            " ORDER BY ts DESC";

        /*

        Archive project
        Move project back to development status
        Move project to Analysis/Cleanup status
        Move project to production status
        Project data set to Editable mode
        Project data set to Read-only/Locked mode
        Project marked as Completed
        Project moved from Completed status back to Analysis/Cleanup status
        Project moved from Completed status back to Production status
        Return project to Production from Analysis/Cleanup status
        Return project to production from inactive status
        Set project as inactive
        Project moved from Completed status back to Development status
        Update record

        */


        //$result = $this->module->query($sql, [$this->pid, $timestamp]);
        //var_dump($result->fetch_assoc());

    }

    /**
     * Get array of users with access to the project at the specified $timestamp.
     * 
     * This includes users currently expired/suspended from the project
     * 
     * @return array users in the project at that time
     */
    function getUsers(): ?array
    {
        $SQL = "SELECT pk FROM " . $this->log_event_table .
            " WHERE log_event_id IN (" .
            " SELECT max(log_event_id) log_event_id FROM " . $this->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_user_rights'" .
            " AND event in ('INSERT', 'DELETE')" .
            " GROUP BY pk)" .
            " AND event = 'INSERT'";
        try {
            $result = $this->module->query($SQL, [$this->pid, $this->timestamp]);
            while ($row = $result->fetch_assoc()) {
                $users[] = $row['pk'];
            }
            return  $users;
        } catch (\Exception $e) {
            $this->module->log('Error fetching users', [
                "pid" => $this->pid,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 
     * 
     * @return array|null
     */
    function getDAGs(): ?array
    {
        $SQL = "SELECT pk,sql_log FROM " . $this->log_event_table .
            " WHERE log_event_id in (" .
            " SELECT max(log_event_id) log_event_id FROM "  . $this->log_event_table .
            " WHERE project_id = ?" .
            " AND ts <= ?" .
            " AND object_type = 'redcap_data_access_groups'" .
            " GROUP BY pk)" .
            " AND description IN ('Create data access group', 'Rename data access group')";
        try {
            $result = $this->module->query($SQL, [$this->pid, $this->timestamp]);
            while ($row = $result->fetch_assoc()) {
                preg_match("/'(?:[^']|'')+'/", $row['sql_log'], $matches);
                $dags[] = [
                    "dag" => $row['pk'],
                    "label" => str_replace("'", "", $matches[0])
                ];
            }
            return $dags;
        } catch (\Exception $e) {
            $this->module->log('Error fetching dags', [
                "pid" => $this->pid,
                "error_message" => $e->getMessage()
            ]);
            return null;
        }
    }
}
