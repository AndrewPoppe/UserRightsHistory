<?php

namespace YaleREDCap\UserRightsHistory;

class Project
{

    private $log_event_table;
    function __construct(UserRightsHistory $module, int $pid)
    {
        $this->pid = $pid;
        $this->module = $module;
        $this->project = $module->getProject($pid);
        $this->log_event_table = $this->project->getLogTable($pid);
        $this->getStatus(date("YmdHis"));
    }

    function getStatus(int $timestamp)
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
}
