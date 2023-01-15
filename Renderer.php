<?php

namespace YaleREDCap\UserRightsHistory;

include_once "User.php";

class Renderer
{
    public $permissions;

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
        <div class="userrights-table-container" style="margin-right: 20px; margin-top:20px;">
            <div style="margin-bottom: 10px;">
                <?= $this->getProjectStatus() ?>
            </div>
            <table id="userrights" class="cell-border stripe compact noOrderIcon">
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
                    $nonCentered = array("user", "expiration", "group", "randomization", "api", "mobile_app", "data_export_rights", "data_viewing_rights");
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
            <div style="margin-top: 10px;">
                <?= $this->getModuleStatus() ?>
            </div>
        </div>
    <?php
    }

    // TODO: instead of having methods in this class for determining whether features are enabled, should that happen elsewhere?

    function getColumns()
    {
        return [
            "role"                    => array("title" => "Role", "show" => true, "width" => 125),
            "user"                    => array("title" => "User", "show" => true, "width" => 225),
            "expiration"              => array("title" => "Expiration", "show" => true, "width" => 50),                                                     // maybe this should be status (expired, suspended, active?)
            "group"                   => array("title" => "Data Access Group", "show" => $this->hasDAGs(), "width" => 100),
            "design"                  => array("title" => "Project Design and Setup", "show" => true, "width" => 50),
            "user_rights"             => array("title" => "User Rights", "show" => true, "width" => 50),
            "data_access_groups"      => array("title" => "Data Access Groups", "show" => true, "width" => 50),
            "data_viewing_rights"     => array("title" => "Data Viewing Rights", "show" => $this->hasGranularExportRights(), "width" => 50),
            "data_export_rights"      => array("title" => "Data Export Rights", "show" => $this->hasGranularExportRights(), "width" => 50),
            "export"                  => array("title" => "Data Export Tool", "show" => !$this->hasGranularExportRights(), "width" => 50),
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
            "record_level_locking"    => array("title" => "Record-Level Locking", "show" => true, "width" => 50),
            "data_entry_rights"       => array("title" => "Data Entry Rights", "show" => !$this->hasGranularExportRights(), "width" => 50)
        ];
    }

    private function hasDAGs()
    {
        $dags = $this->permissions["dags"];
        return !is_null($dags) && count($dags) > 0;
    }

    private function hasGranularExportRights()
    {
        $user = reset($this->permissions["users"]);
        return isset($user["data_export_instruments"]);
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
        return $enabledSystem && $enabledProject;
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
        $this->insertImage("tick.png");
    }

    private function insertX()
    {
        $this->insertImage("cross.png");
    }

    private function insertCheckShield()
    {
        $this->insertImage("tick_shield.png");
    }

    private function insertImage(string $filename)
    {
        echo "<div style='text-align:center;'><img src='" . APP_PATH_IMAGES . $filename . "'></img></div>";
    }

    function parsePermissions()
    {
        $roles = &$this->permissions["roles"];
        $users = &$this->permissions["users"];

        foreach ($users as &$user) {
            $role_id = $user["role_id"];
            if ($role_id != null) {
                if ($roles[$role_id]["users"] == null) {
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
        $row["role"] = $this->getRoleText($data, $isUser);

        // user array
        $users = $this->getUserArray($data, $isUser);

        // User
        $row["user"] = $this->getUserText($users, $isUser);

        // Expiration
        $row["expiration"] = $this->getExpirationText($users);

        // Data Access Group
        $row["group"] = $this->getDAGText($users);

        // Project Design and Setup
        $row["design"] = $this->getCheckOrX($data["design"]);

        // User Rights
        $row["user_rights"] = $this->getCheckOrX($data["user_rights"]);

        // Data Access Groups
        $row["data_access_groups"] = $this->getCheckOrX($data["data_access_groups"]);

        // Data Viewing Rights
        $row["data_viewing_rights"] = $this->getDataViewingRightsText($data["data_entry"]);

        // Data Export Rights
        $row["data_export_rights"] = $this->getDataExportRightsText($data["data_export_instruments"]);

        // Data Export Tool
        $row["export"] = $this->getDataExportText($data["data_export_tool"]);

        // Reports & Report Builder
        $row["reports"] = $this->getCheckOrX($data["reports"]);

        // Graphical Data View & Stats
        $row["graphical"] = $this->getCheckOrX($data["graphical"]);

        // Survey Distribution Tools
        $row["surveys"] = $this->getCheckOrX($data["participants"]);

        // Calendar & Scheduling
        $row["calendar"] = $this->getCheckOrX($data["calendar"]);

        // Data Import Tool
        $row["import"] = $this->getCheckOrX($data["data_import_tool"]);

        // Data Comparison Tool
        $row["comparison"] = $this->getCheckOrX($data["data_comparison_tool"]);

        // Logging
        $row["logging"] = $this->getCheckOrX($data["data_logging"]);

        // File Repository
        $row["file_repository"] = $this->getCheckOrX($data["file_repository"]);

        // Double Data Entry
        $row["dde"] = $this->getDDEText($data["double_data"]);

        // Record Locking Customization
        $row["lock_record_customize"] = $this->getCheckOrX($data["lock_record_customize"]);

        // Lock/Unlock Records
        $row["lock_record"] = $this->getLockRecordText($data["lock_record"]);

        // Randomization
        $row["randomization"] = $this->getRandomizationText($data);

        // Data Quality (create/edit rules)
        $row["data_quality_design"] = $this->getCheckOrX($data["data_quality_design"]);

        // Data Quality (execute rules)
        $row["data_quality_execute"] = $this->getCheckOrX($data["data_quality_execute"]);

        // Data Resolution Workflow
        $row["data_quality_resolution"] = $this->getDataResolutionText($data["data_quality_resolution"]);

        // API
        $row["api"] = $this->getAPIText($data);

        // REDCap Mobile App
        $row["mobile_app"] = $this->getMobileAppText($data);

        // Clinical Data Pull from EHR (Setup / Mapping)
        $row["cdp_mapping"] = $this->getCheckOrX($data["realtime_webservice_mapping"]);

        // Clinical Data Pull from EHR (Adjudicate Data)
        $row["cdp_adjudicate"] = $this->getCheckOrX($data["realtime_webservice_adjudicate"]);

        // DTS (Data Transfer Services)
        $row["dts"] = $this->getCheckOrX($data["dts"]);

        // Create Records
        $row["record_create"] = $this->getCheckOrX($data["record_create"]);

        // Rename Records
        $row["record_rename"] = $this->getCheckOrX($data["record_rename"]);

        // Delete Records
        $row["record_delete"] = $this->getCheckOrX($data["record_delete"]);

        // Record Level Locking
        $row["record_level_locking"] = $this->getCheckOrX($data["lock_record_multiform"]);

        // Data Entry Rights
        $row["data_entry_rights"] = $this->getDataEntryRightsText($data);

        return $row;
    }

    private function getCheckOrX($test)
    {
        return [$test ? "check" : "X"];
    }

    private function getRoleText(array $data, bool $isUser)
    {
        $roleText = $isUser ? "<span style='color:lightgray;'>—</span>" : "<span style='font-weight:bold; color:#800000;'>" . $data["role_name"] . "</span>&nbsp;[" . $data["role_id"] . "]";
        return ['<div style="display:flex; align-items:center; justify-content:center;">' . $roleText . '</div>'];
    }

    private function getUserArray(array $data, bool $isUser)
    {
        $users = array();
        if ($isUser) {
            $users[] = new User($data, $this);
        } else {
            foreach ($data["users"] as $thisUserData) {
                $users[] = new User($thisUserData, $this);
            }
        }
        return $users;
    }

    private function getUserText(array $users, bool $isUser)
    {
        $userData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $userData[] = "<hr>";
            }
            $userData[] = $user->getUserText();
        }
        if (!$isUser && empty($userData)) {
            $userData[] = "<span style='color:#999; font-size:75%;'>[No users assigned]</span>";
        }
        return $userData;
    }

    private function getExpirationText(array $users)
    {
        $expirationData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $expirationData[] = "<hr>";
            }
            $expiration_string = $user->getExpirationDate();
            $expirationData[] = $this->createExpirationDate($expiration_string);
        }
        return $expirationData;
    }

    private function getDAGText(array $users)
    {
        $dagData = array();
        foreach ($users as $index => $user) {
            if ($index !== array_key_first($users)) {
                $dagData[] = "<hr>";
            }
            $dagData[] = $user->getDagText();
        }
        return $dagData;
    }

    private function getRandomizationText(array $data)
    {
        $result = array();
        if ($data["random_setup"]) {
            $result[] = "<div style='text-align:center;'>Setup</div>";
        }
        if ($data["random_dashboard"]) {
            $result[] = "<div style='text-align:center;'>Dashboard</div>";
        }
        if ($data["random_perform"]) {
            $result[] = "<div style='text-align:center;'>Randomize</div>";
        }
        return $result;
    }

    private function createExpirationDate($date_string)
    {
        if (!$date_string) {
            return "<div style='display:flex; align-items:center; justify-content:center;'>&nbsp;<span style='font-size:11px; color:#999;'>never</span>&nbsp;</div>";
        }
        $date = date_create($date_string);
        $now_string = date("Y-m-d", $this->permissions["timestamp"]);
        $now = date_create($now_string);
        $diff = date_diff($now, $date);
        $formatted_date = $date->format("m/d/Y");
        $color = (!$diff->invert) ? "black" : "red";
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
        return [$result];
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
        return [$result];
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
        return [$result];
    }

    private function getDataResolutionText($value)
    {
        $result = "";
        switch (strval($value)) {
            case "0":
                $result = "X";
                break;
            case "1":
                $result = "View only";
                break;
            case "2":
                $result = "Respond only to opened queries";
                break;
            case "3":
                $result = "Open, close, and respond to queries";
                break;
            case "4":
                $result = "Open queries only";
                break;
            case "5":
                $result = "Open and respond to queries";
                break;
            default:
                $result = "X";
        }
        return [$result];
    }

    private function getAPIText(array $data): array
    {
        $import = $data["api_import"];
        $export = $data["api_export"];
        $exportText = "<div style='text-align:center;'>Export</div>";
        $importText = "<div style='text-align:center;'>Import</div>";
        if ($import && $export) {
            $result = [
                $exportText,
                $importText,
            ];
        } elseif ($import) {
            $result = [$importText];
        } elseif ($export) {
            $result = [$exportText];
        } else {
            $result = ["X"];
        }
        return $result;
    }

    private function getMobileAppText(array $data): array
    {
        $app = $data["mobile_app"];
        $download = $data["mobile_app_download_data"];
        $appText = "check";
        $downloadText = "<div style='text-align:center;'>Download all data</div>";
        if ($app && $download) {
            $result = [$appText, $downloadText];
        } else if ($app) {
            $result = [$appText];
        } else if ($download) {
            $result = [$downloadText];
        } else {
            $result = ["X"];
        }
        return $result;
    }

    private function parseInstrumentRightsString(?string $string): array
    {
        $trimmed = substr(trim($string), 1, -1);
        $forms = explode("][", $trimmed);
        $result = [
            "by_permission" => [
                "0" => [],
                "1" => [],
                "2" => [],
                "3" => []
            ],
            "by_instrument" => []
        ];
        foreach ($forms as $form) {
            $split = explode(",", $form);
            $result["by_permission"][strval($split[1])][] = $split[0];
            $result["by_instrument"][$split[0]] = $split[1];
        }
        return $result;
    }

    private function hasSurveys()
    {
        $hasSurveys = false;
        foreach ($this->permissions["instruments"] as $instrument) {
            if (!is_null($instrument["survey_id"])) {
                $hasSurveys = true;
            }
        }
        return $hasSurveys;
    }

    private function getDataEntryRightsText(array $data): array
    {
        $string = $data["data_entry"];
        $allInstruments = $this->permissions["instruments"];
        $instruments = $this->parseInstrumentRightsString($string);
        $surveysHeader = $this->getSurveyHeader();
        $cell = "<a tabindex='0' style='color:#333; text-decoration:underline;' class='popoverspan' data-toggle='popover' data-trigger='focus' title='Data Entry Rights' data-content='<div class=\"popover-table\"><table class=\"table\"><thead><tr><th></th><th>No Access</th><th>Read Only</th><th>View & Edit</th>${surveysHeader}</tr></thead><tbody>";
        $tdStart = "<td><i style=\"color:#666;\" class=\"";
        $tdEnd = " fa-circle\"></i></td>";
        foreach ($allInstruments as $thisInstrument) {
            $instrument = $thisInstrument["id"];
            $permission = $instruments["by_instrument"][$instrument] ?? "1";
            $isSurvey = !is_null($thisInstrument["survey_id"]);
            $instrumentTitle = $thisInstrument["title"];
            $instrumentText = $instrumentTitle . ($isSurvey ? "<span style=\"font-weight:normal; font-size:10px; color:red;\"> [survey]</span>" : "") . "<br><span style=\"font-weight:normal;\">(${instrument})</span>";
            $cell .= "<tr><th>${instrumentText}</th>";
            $cell .= $tdStart . ($permission == 0 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . ($permission == 2 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . (($permission == 1 || $permission == 3) ? "fas" : "far") . $tdEnd;

            $cell .= $this->getSurveyTD($isSurvey, $permission);

            $cell .= "</tr>";
        }
        $cell .= "</tbody></table></div>'>Rights</a>";

        return [$cell];
    }

    private function getDataExportRightsText(?string $string): array
    {
        $instruments = $this->parseInstrumentRightsString($string);
        $allInstruments = $this->permissions["instruments"];
        $cell = "<div tabindex='0' class='popoverspan instrumentCell' style='cursor: pointer;' data-toggle='popover' data-trigger='focus' title='Data Export Rights' data-content='<div class=\"popover-table\"><table class=\"table\"><thead><tr><th></th><th>No Access</th><th>De-Identified</th><th>Remove All Identifier Fields</th><th>Full Data Set</th></tr></thead><tbody>";
        $tdStart = "<td><i style=\"color:#666;\" class=\"";
        $tdEnd = " fa-circle\"></i></td>";
        foreach ($allInstruments as $thisInstrument) {
            $instrument = $thisInstrument["id"];
            $permission = $instruments["by_instrument"][$instrument] ?? "1";
            $instrumentTitle = $thisInstrument["title"];
            $instrumentText = $instrumentTitle . "<br><span style=\"font-weight:normal;\">(${instrument})</span>";
            $cell .= "<tr><th>${instrumentText}</th>";
            $cell .= $tdStart . ($permission == 0 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . ($permission == 2 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . ($permission == 3 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . ($permission == 1 ? "fas" : "far") . $tdEnd;
            $cell .= "</tr>";
        }
        $cell .= "</tbody></table></div>'>";

        $nNoAccess = count($instruments["by_permission"][0]);
        $nFullData = count($instruments["by_permission"][1]);
        $nDeidentified = count($instruments["by_permission"][2]);
        $nIdentRemoved = count($instruments["by_permission"][3]);
        $cell_line_prefix = "<div class='userRightsTableForms'><code>";

        if ($nNoAccess > 0) {
            $cell .= $cell_line_prefix . $nNoAccess . "</code> No Access</div>";
        }
        if ($nDeidentified > 0) {
            $cell .= $cell_line_prefix . $nDeidentified . "</code> De-Identified</div>";
        }
        if ($nIdentRemoved > 0) {
            $cell .= $cell_line_prefix . $nIdentRemoved . "</code> Remove All Identifier Fields</div>";
        }
        if ($nFullData > 0) {
            $cell .= $cell_line_prefix . $nFullData . "</code> Full Data Set</div>";
        }
        $cell .= "</div>";
        return [$cell];
    }

    private function getDataViewingRightsText(?string $string): array
    {
        $instruments = $this->parseInstrumentRightsString($string);
        $allInstruments = $this->permissions["instruments"];
        $surveysHeader = $this->getSurveyHeader();
        $cell = "<div tabindex='0' class='popoverspan instrumentCell' style='cursor: pointer;' data-toggle='popover' data-trigger='focus' title='Data Viewing Rights' data-content='<div class=\"popover-table\"><table class=\"table\"><thead><tr><th></th><th>No Access</th><th>Read Only</th><th>View & Edit</th>${surveysHeader}</tr></thead><tbody>";
        $tdStart = "<td><i style=\"color:#666;\" class=\"";
        $tdEnd = " fa-circle\"></i></td>";
        foreach ($allInstruments as $thisInstrument) {
            $instrument = $thisInstrument["id"];
            $permission = $instruments["by_instrument"][$instrument] ?? "1";
            $isSurvey = !is_null($thisInstrument["survey_id"]);
            $instrumentTitle = $thisInstrument["title"];
            $instrumentText = $instrumentTitle . ($isSurvey ? "<span style=\"font-weight:normal; font-size:10px; color:red;\"> [survey]</span>" : "") . "<br><span style=\"font-weight:normal;\">(${instrument})</span>";
            $cell .= "<tr><th>${instrumentText}</th>";
            $cell .= $tdStart . ($permission == 0 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . ($permission == 2 ? "fas" : "far") . $tdEnd;
            $cell .= $tdStart . (($permission == 1 || $permission == 3) ? "fas" : "far") . $tdEnd;

            $cell .= $this->getSurveyTD($isSurvey, $permission);

            $cell .= "</tr>";
        }
        $cell .= "</tbody></table></div>'>";

        $nNoAccess = count($instruments["by_permission"][0]);
        $nViewAndEdit = count($instruments["by_permission"][1]);
        $nReadOnly = count($instruments["by_permission"][2]);
        $nEditSurvey = count($instruments["by_permission"][3]);

        $cell_line_prefix = "<div class='userRightsTableForms'><code>";

        if ($nNoAccess > 0) {
            $cell .= $cell_line_prefix . $nNoAccess . "</code> No Access (Hidden)</div>";
        }
        if ($nReadOnly > 0) {
            $cell .= $cell_line_prefix . $nReadOnly . "</code> Read Only</div>";
        }
        if ($nViewAndEdit > 0 || $nEditSurvey > 0) {
            $cell .= $cell_line_prefix . ($nViewAndEdit + $nEditSurvey) . "</code> View & Edit</div>";
        }
        $cell .= "</div>";
        return [$cell];
    }

    private function getSurveyHeader()
    {
        return $this->hasSurveys() ? "<th>Edit survey responses</th>" : "";
    }

    private function getSurveyTD($isSurvey, $permission)
    {
        $result = "";
        if ($isSurvey) {
            $result .= "<td><i style=\"color:#666;\" class=" . ($permission == 3 ? "\"fas fa-check-square\"" : "\"far fa-square\"") . "></i></td>";
        } elseif ($this->hasSurveys()) {
            $result .= "<td></td>";
        }
        return $result;
    }

    private function getProjectStatus(): string
    {
        $project_data = $this->permissions["project_status"];
        $status = $project_data["status"];
        $statusText = "<span><span style='color:#000; font-weight: bold;'>Project Status:</span>&nbsp; ";
        switch ($status) {
            case 0:
                $statusText .= "<span style='color:#666;'><i class='fas fa-wrench'></i> Development</span></span>";
                break;
            case 1:
                $statusText .= "<span style='color:#00A000;'><i class='far fa-check-square'></i> Production</span></span>";
                break;
            case 2:
                if (!empty($project_data["completed_time"])) {
                    $statusText .= "<i class='fas fa-archive' style='color:#dc3545;'></i>&nbsp; <span style='color: #dc3545; background-color:#f0f0f0; border:1px solid #ddd; border-radius: 5px; border-collapse: collapse; font-family:Menlo,Monaco,Consolas,\"Courier New\",monospace; padding: 2px 3px 2px 3px;'>Completed</span></span>";
                } elseif ($project_data["data_locked"]) {
                    $statusText .= "<span style='color:#A00000; font-weight:bold;'>Analysis/Cleanup - </span> <span style='font-weight:bold; color: #C00000;'><i class='fas fa-lock'></i> Read-only / Locked</span></span>";
                } else {
                    $statusText .= "<span style='color:#A00000; font-weight:bold;'>Analysis/Cleanup - </span> <span style='font-weight:bold; color: #05a005;'><i class='fas fa-edit'></i> Editable (existing records only)</span></span>";
                }
                break;
            default:
                $statusText = " unknown</span>";
        }
        return $statusText;
    }

    private function getModuleStatus(): string
    {
        $module_status = $this->permissions["module_status"];
        return $module_status === 1 ? "" : "<span style='color: #990000;'><span style='font-weight: bold;'>Warning:</span> the module was not enabled at this point in time. The above permissions may not be accurate.</span>";
    }
}
