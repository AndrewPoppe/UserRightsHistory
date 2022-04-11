<?php

namespace YaleREDCap\UserRightsHistory;

include_once "User.php";

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
        $columns = $this->getColumns();
        $this->parsePermissions();
?>
        <style>
            table {
                border-collapse: collapse;
            }

            td,
            th {
                border-right: solid 1px #f0f0f0;
                border-left: solid 1px #f0f0f0;
            }
        </style>
        <table class="table stripe hover display compact">
            <thead>
                <tr>
                    <?php foreach ($columns as $column) {
                        $this->makeHeader($column);
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $nonCentered = array("user", "expiration", "group", "randomization");
                foreach ($this->permissions["users"] as $user) {
                    echo "<tr>";
                    foreach ($columns as $column_id => $column) {
                        $center = !in_array($column_id, $nonCentered, true);
                        $data = $user["row"][$column_id];
                        if (!$column["show"] || is_null($data)) {
                            continue;
                        }
                        $this->makeCell($data, $center);
                    }
                    echo "</tr>";
                }
                foreach ($this->permissions["roles"] as $role) {
                    echo "<tr>";
                    foreach ($columns as $column_id => $column) {
                        $center = !in_array($column_id, $nonCentered, true);
                        $data = $role["row"][$column_id];
                        if (!$column["show"] || is_null($data)) {
                            continue;
                        }
                        $this->makeCell($data, $center);
                    }
                    echo "</tr>";
                } ?>
            </tbody>
        </table>
    <?php
    }

    // TODO: instead of having methods in this class for determining whether features are enabled, should that happen elsewhere?

    function getColumns()
    {
        return [
            "role"                    => array("title" => "Role", "show" => true, "width" => 100),
            "user"                    => array("title" => "User", "show" => true, "width" => 200),
            "expiration"              => array("title" => "Expiration", "show" => true, "width" => 50),                                                // maybe this should be status (expired, suspended, active?)
            "group"                   => array("title" => "Data Access Group", "show" => $this->hasDAGs(), "width" => 150),
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
        return count($this->permissions["dags"]) > 0;
    }

    private function graphicalEnabled()
    {
        return boolval($this->permissions["system"]["enable_plotting"]);
    }

    private function surveysEnabled()
    {
        return boolval($this->permissions["project_status"]["surveys_enabled"]);
    }

    private function ddeEnabled()
    {
        return boolval($this->permissions["project_status"]["double_data_entry"]);
    }

    private function randomizationEnabled()
    {
        return boolval($this->permissions["project_status"]["randomization"]);
    }

    private function dataResolutionWorkflowEnabled()
    {
        return $this->permissions["project_status"]["data_resolution_enabled"] == 2;
    }

    private function apiEnabled()
    {
        return boolval($this->permissions["system"]["api_enabled"]);
    }

    private function mobileAppEnabled()
    {
        $apiEnabled = $this->apiEnabled();
        $mobileAppEnabled = $this->permissions["system"]["mobile_app_enabled"];
        return $apiEnabled && $mobileAppEnabled;
    }

    private function cdpEnabled()
    {
        $enabledSystem = $this->permissions["system"]["fhir_ddp_enabled"];
        $enabledProject = $this->permissions["project_status"]["realtime_webservice_enabled"];
        return $enabledProject && $enabledProject;
    }

    private function dtsEnabled()
    {
        $enabledSystem = $this->permissions["system"]["dts_enabled_global"];
        $enabledProject = $this->permissions["project_status"]["dts_enabled"];
        return $enabledSystem && $enabledProject;
    }


    private function makeHeader($column)
    {
        if (!$column["show"]) {
            return;
        }
    ?>
        <th style="min-width:<?= $column["width"] ?>px; text-align:center; vertical-align:middle;">
            <?= $column["title"] ?>
        </th>
<?php
    }

    private function makeCell(array $content, bool $center = true)
    {
        echo "<td style='vertical-align:middle;'>";
        echo !$center ? "" : "<div style='display:flex; align-items:center; justify-content:center;'>";
        $pCenter = $center ? "text-align:center;" : "";
        foreach ($content as $item) {
            if ($item === "check") {
                $this->insertCheck();
            } elseif ($item === "X") {
                $this->insertX();
            } elseif ($item === "checkshield") {
                $this->insertCheckShield();
            } else {
                echo "<p style='${pCenter}'>${item}</p>";
            }
        }
        echo ($center ? "</div>" : "") . "</td>";
    }


    private function insertCheck()
    {
        echo "<img src='" . APP_PATH_IMAGES . "tick.png'></img>";
    }

    private function insertX()
    {
        echo "<img src='" . APP_PATH_IMAGES . "cross.png'></img>";
    }

    private function insertCheckShield()
    {
        echo "<img src='" . APP_PATH_IMAGES . "tick_shield.png'></img>";
    }

    function parsePermissions()
    {
        $roles = &$this->permissions["roles"];
        $users = &$this->permissions["users"];

        foreach ($users as &$user) {
            $role_id = $user["role_id"];
            if ($role_id !== null) {
                if ($roles[$role_id]["users"] === null) {
                    $roles[$role_id]["users"] = array();
                }
                $roles[$role_id]["users"][$user["username"]] = $user;
                unset($users[$user["username"]]);
                continue;
            }
            $user["row"] = $this->parseRow($user, true);
        }

        foreach ($roles as &$role) {
            $role["row"] = $this->parseRow($role, false);
        }
    }

    /**
     * @param array $data user or role permissions raw data
     * @param bool $isUser Whether the data is from a user. False is for role.
     * 
     * @return array parsed row for table
     */
    private function parseRow(array $data, bool $isUser): array
    {

        $row = array();

        // Role
        $roleText = $isUser ? "<span style='color:lightgray;'>—</span>" : "<span style='font-weight:bold; color:#800000;'>" . $data["role_name"] . "</span>&nbsp;[" . $data["role_id"] . "]";
        $wrappedRoleText = ['<div style="display:flex; align-items:center; justify-content:center;">' . $roleText . '</div>'];
        $row["role"] = $wrappedRoleText;

        $users = array();
        if ($isUser) {
            $users[] = new User($data, $this);
        } else {
            foreach ($data["users"] as $thisUserData) {
                $users[] = new User($thisUserData, $this);
            }
        }

        // User
        $userData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $userData[] = "<hr>";
            }
            $userData[] = $user->getUserText();
        }
        if (!$isUser && empty($userData)) {
            $userData[] = "<span style='color:lightgrey; font-size:75%;'>[No users assigned]</span>";
        }
        $row["user"] = $userData;

        // Expiration
        $expirationData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $expirationData[] = "<hr>";
            }
            $expiration_string = $user->getExpirationDate();
            $expirationData[] = $this->createExpirationDate($expiration_string);
        }
        $row["expiration"] = $expirationData;

        // Data Access Group
        $dagData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $dagData[] = "<hr>";
            }
            $dagData[] = $user->getDagText();
        }
        $row["group"] = $dagData;

        // Project Design and Setup
        $row["design"] = [$data["design"] ? "check" : "X"];

        // User Rights
        $row["user_rights"] = [$data["user_rights"] ? "check" : "X"];

        // Data Access Groups
        $row["data_access_groups"] = [$data["data_access_groups"] ? "check" : "X"];

        // Data Export Tool
        $row["export"] = [$this->getDataExportText($data["data_export_tool"])];

        // Reports & Report Builder
        $row["reports"] = [$data["reports"] ? "check" : "X"];

        // Graphical Data View & Stats
        $row["graphical"] = [$data["graphical"] ? "check" : "X"];

        // Survey Distribution Tools
        $row["surveys"] = [$data["participants"] ? "check" : "X"];

        // Calendar & Scheduling
        $row["calendar"] = [$data["calendar"] ? "check" : "X"];

        // Data Import Tool
        $row["import"] = [$data["data_import_tool"] ? "check" : "X"];

        // Data Comparison Tool
        $row["comparison"] = [$data["data_comparison_tool"] ? "check" : "X"];

        // Logging
        $row["logging"] = [$data["data_logging"] ? "check" : "X"];

        // File Repository
        $row["file_repository"] = [$data["file_repository"] ? "check" : "X"];

        // Double Data Entry
        $row["dde"] = [$this->getDDEText($data["double_data"])];

        // Record Locking Customization
        $row["lock_record_customize"] = [$data["lock_record_customize"] ? "check" : "X"];

        // Lock/Unlock Records
        $row["lock_record"] = [$this->getLockRecordText($data["lock_record"])];

        // Randomization
        $row["randomization"] = array();
        if ($data["random_dashboard"]) {
            $row["randomization"][] = "Dashboard";
        }
        if ($data["random_setup"]) {
            $row["randomization"][] = "Setup";
        }
        if ($data["random_perform"]) {
            $row["randomization"][] = "Randomize";
        }


        return $row;
    }


    private function createExpirationDate($date_string)
    {
        if (is_null($date_string)) {
            return "<div style='display:flex; align-items:center; justify-content:center;'><span style='font-size:small; color:lightgrey;'>never</span></div>";
        }
        $date = date_create($date_string);
        $now_string = date("Y-m-d", $this->permissions["timestamp"]);
        $now = date_create($now_string);
        $diff = date_diff($now, $date);
        $formatted_date = $date->format("m/d/Y");
        $color = (!$diff->invert) ? "black" : "tomato";
        return "<div style='display:flex; align-items:center; justify-content:center;'><span style='color:${color};'>${formatted_date}</span></div>";
    }

    private function getDataExportText($value)
    {
        $result = "";
        switch (strval($value)) {
            case "0":
                $result = "X";
                break;
            case "1":
                $result = "Full Data Set";
                break;
            case "2":
                $result = "De-identified";
                break;
            case "3":
                $result = "Remove all tagged Identifier fields";
                break;
            default:
                $result = "X";
        }
        return $result;
    }

    private function getDDEText($value)
    {
        $result = "";
        switch (strval($value)) {
            case "0":
                $result = "Reviewer";
                break;
            case "1":
                $result = "DDE Person #1";
                break;
            case "2":
                $result = "DDE Person #2";
                break;
            default:
                $result = "X";
        }
        return $result;
    }

    private function getLockRecordText($value)
    {
        $result = "";
        switch (strval($value)) {
            case "0":
                $result = "X";
                break;
            case "1":
                $result = "check";
                break;
            case "2":
                $result = "checkshield";
                break;
            default:
                $result = "X";
        }
        return $result;
    }
}
