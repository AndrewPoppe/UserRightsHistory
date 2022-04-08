<?php

namespace YaleREDCap\UserRightsHistory;

class Renderer
{
    function __construct($permissions)
    {
        $this->permissions = $permissions;
    }

    function print()
    {
        var_dump($this->permissions);
    }


    function renderTable()
    {
    }

    // TODO: instead of having methods in this class for determining whether features are enabled, should that happen elsewhere?
    // TODO: Are there system settings that determine whether these should be shown that are not currently being stored?

    function getColumns()
    {
        return [
            "role"                    => array("title" => "Role", "show" => true, "width" => 50),
            "user"                    => array("title" => "User", "show" => true, "width" => 50),
            "expiration"              => array("title" => "Expiration Date", "show" => true, "width" => 50),                                                // maybe this should be status (expired, suspended, active?)
            "group"                   => array("title" => "Group (DAG)", "show" => $this->hasDAGs(), "width" => 50),
            "design"                  => array("title" => "Project Design and Setup", "show" => true, "width" => 50),
            "user_rights"             => array("title" => "User Rights", "show" => true, "width" => 50),
            "data_access_groups"      => array("title" => "Data Access Groups", "show" => true, "width" => 50),
            "export"                  => array("title" => "Data Export Tool", "show" => true, "width" => 50),
            "reports"                 => array("title" => "Reports & Report Builder", "show" => true, "width" => 50),
            "graphical"               => array("title" => "Graphical Data View & Stats", "show" => $this->graphicalEnabled(), "width" => 50),
            "surveys"                 => array("title" => "Survey Distribution Tools", "show" => $this->surveysEnabled(), "width" => 50),
            "calendar"                => array("title" => "Calendar & Scheduling", "show" => true, "width" => 50),
            "import"                  => array("title" => "Data Import Tool", "show" => true, "width" => 50),
            "comparison"              => array("title" => "Data Comparison Tool", "show" => true, "width" => 50),
            "logging"                 => array("title" => "Logging", "show" => true, "width" => 50),
            "file_repository"         => array("title" => "File Repository", "show" => true, "width" => 50),
            "dde"                     => array("title" => "Double Data Entry", "show" => $this->ddeEnabled(), "width" => 50),
            "lock_record_customize"   => array("title" => "Record Locking Customization", "show" => true, "width" => 50),
            "lock_record"             => array("title" => "Lock/Unlock Records", "show" => true, "width" => 50),
            "randomization"           => array("title" => "Randomization", "show" => $this->randomizationEnabled(), "width" => 50),
            "data_quality_design"     => array("title" => "Data Quality (create/edit rules)", "show" => true, "width" => 50),
            "data_quality_execute"    => array("title" => "Data Quality (execute rules)", "show" => true, "width" => 50),
            "data_quality_resolution" => array("title" => "Data Resolution Workflow", "show" => $this->dataResolutionWorkflowEnabled(), "width" => 50),
            "api"                     => array("title" => "API", "show" => $this->apiEnabled(), "width" => 50),
            "mobile_app"              => array("title" => "REDCap Mobile App", "show" => $this->mobileAppEnabled(), "width" => 50),
            "cdp_mapping"             => array("title" => "Clinical Data Pull from EHR (Setup / Mapping)", "show" => $this->cdpEnabled(), "width" => 50),
            "cdp_adjudicate"          => array("title" => "Clinical Data Pull from EHR (Adjudicate Data)", "show" => $this->cdpEnabled(), "width" => 50),
            "dts"                     => array("title" => "DTS (Data Transfer Services)", "show" => $this->dtsEnabled(), "width" => 50),
            "record_create"           => array("title" => "Create Records", "show" => true, "width" => 50),
            "record_rename"           => array("title" => "Rename Records", "show" => true, "width" => 50),
            "record_delete"           => array("title" => "Delete Records", "show" => true, "width" => 50),
        ];
    }

    private function hasDAGs()
    {
        return true;
    }

    private function graphicalEnabled()
    {
        return true;
    }

    private function surveysEnabled()
    {
        return true;
    }

    private function ddeEnabled()
    {
        return true;
    }

    private function randomizationEnabled()
    {
        return true;
    }

    private function dataResolutionWorkflowEnabled()
    {
        return true;
    }

    private function apiEnabled()
    {
        return true;
    }

    private function mobileAppEnabled()
    {
        return true;
    }

    private function cdpEnabled()
    {
        return true;
    }

    private function dtsEnabled()
    {
        return true;
    }
}
