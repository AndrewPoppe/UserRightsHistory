<?php

namespace YaleREDCap\UserRightsHistory;

class CsvCreator
{
    private $permissions;
    public $renderer;
    public function __construct($permissions)
    {
        $this->permissions = $permissions;
        $this->renderer    = new Renderer($permissions);
    }

    /**
     * Convert right name from column format to either CSV format (suitable for import) or internal storage format (user data)
     * @param string $rightName
     * @param bool $toCsv If true, converts from column format to CSV format. If false, converts from column format to internal storage format (user data).
     * @return string Converted right name
     */
    public static function convertRightName(string $rightName, bool $toCsv = true)
    {

        $csvConversions = [
            'user'                  => 'username',
            'group'                 => 'data_access_group',
            'graphical'             => 'stats_and_charts',
            'surveys'               => 'manage_survey_participants',
            'import'                => 'data_import_tool',
            'comparison'            => 'data_comparison_tool',
            'data_quality_design'   => 'data_quality_create',
            'record_level_locking'  => 'lock_records_all_forms',
            'dde'                   => 'double_data',
            'data_viewing_rights'   => 'forms',
            'data_export_rights'    => 'forms_export',
            'lock_record'           => 'lock_records',
            'lock_record_customize' => 'lock_records_customization'
        ];

        $userConversions = [
            'user'                 => 'username',
            'role'                 => 'role_id',
            'group'                => 'group_id',
            'surveys'              => 'participants',
            'import'               => 'data_import_tool',
            'comparison'           => 'data_comparison_tool',
            'logging'              => 'data_logging',
            'record_level_locking' => 'lock_record_multiform',
            'data_viewing_rights'  => 'data_entry',
            'data_export_rights'   => 'data_export_instruments',
            'dde'                  => 'double_data'
        ];

        return $toCsv ? $csvConversions[$rightName] ?? $rightName : $userConversions[$rightName] ?? $rightName;
    }

    private function reformatFormsString($val)
    {
        if ( empty($val) ) {
            return $val;
        }
        $trimmed = substr(trim($val), 1, -1);
        $forms   = explode("][", $trimmed);
        $res     = [];
        foreach ( $forms as $form ) {
            $split = explode(",", $form);
            $res[] = $split[0] . ':' . $split[1];
        }
        $val = implode(",", $res);
        return $val;
    }

    public function createUsersCsv()
    {
        $csvParts = $this->createUsersArray();
        return $this->createCsvFromArray($csvParts);
    }

    public function createUsersArray()
    {
        $allColumns = $this->renderer->getColumns();
        $columns    = array();
        foreach ( $allColumns as $column => $data ) {

            $toInclude1 = $data["show"] && in_array($column, [ 'mycap_participants', 'data_quality_resolution', 'dde' ], true);
            $toInclude2 = in_array($column, [
                'user',
                'expiration',
                'design',
                'alerts',
                'user_rights',
                'data_access_groups',
                'reports',
                'graphical',
                'surveys',
                'calendar',
                'import',
                'comparison',
                'logging',
                'file_repository',
                'data_quality_design',
                'data_quality_execute',
                'record_create',
                'record_rename',
                'record_delete',
                'record_level_locking',
                'lock_record',
                'lock_record_customize',
                'data_viewing_rights',
                'data_export_rights'
            ], true);
            if ( $toInclude1 || $toInclude2 ) {
                $columns[$column] = $this->convertRightName($column);
            }
            if ( $column == 'group' ) {
                $columns['group']    = 'data_access_group';
                $columns['group_id'] = 'data_access_group_id';
            }
            if ( $column == 'api' ) {
                $columns['api_export'] = 'api_export';
                $columns['api_import'] = 'api_import';
            }
            if ( $column == 'mobile_app' ) {
                $columns['mobile_app']               = 'mobile_app';
                $columns['mobile_app_download_data'] = 'mobile_app_download_data';
            }
            if ( $column == 'randomization' && $data["show"] ) {
                $columns['random_setup']     = 'random_setup';
                $columns['random_dashboard'] = 'random_dashboard';
                $columns['random_perform']   = 'random_perform';
            }
        }

        $rows = array();
        foreach ( $this->permissions["users"] as $user ) {
            $row = array();
            foreach ( $columns as $column => $csvColumn ) {
                $val = (string) $user[$this->convertRightName($column, false)];
                if ( $column == 'group' && !empty($user['group_id']) ) {
                    $val = $this->permissions['dags'][(int) $user['group_id']]['group_name'];
                }
                if ( $csvColumn == 'forms' || $csvColumn == 'forms_export' ) {
                    $val = $this->reformatFormsString($val);
                }
                // if ( str_contains($val, ",") ) {
                //     $val = '"' . $val . '"';
                // }
                $row[] = $val;
            }
            $rows[] = $row;
        }

        return [ 'header' => array_values($columns), 'rows' => $rows ];
    }

    public function createRolesCsv()
    {
        $csvParts = $this->createRolesArray();
        return $this->createCsvFromArray($csvParts);
    }

    public function createRolesArray()
    {
        $allColumns = $this->renderer->getColumns();
        $columns    = array();
        foreach ( $allColumns as $column => $data ) {

            $toInclude1 = $data["show"] && in_array($column, [ 'mycap_participants', 'data_quality_resolution', 'dde' ], true);
            $toInclude2 = in_array($column, [
                'design',
                'alerts',
                'user_rights',
                'data_access_groups',
                'reports',
                'graphical',
                'surveys',
                'calendar',
                'import',
                'comparison',
                'logging',
                'file_repository',
                'data_quality_design',
                'data_quality_execute',
                'record_create',
                'record_rename',
                'record_delete',
                'record_level_locking',
                'lock_record',
                'lock_record_customize',
                'data_viewing_rights',
                'data_export_rights'
            ], true);
            if ( $toInclude1 || $toInclude2 ) {
                $columns[$column] = $this->convertRightName($column);
            }
            if ( $column == 'role' ) {
                $columns['unique_role_name'] = 'unique_role_name';
                $columns['role_name']        = 'role_label';
            }
            if ( $column == 'api' ) {
                $columns['api_export'] = 'api_export';
                $columns['api_import'] = 'api_import';
            }
            if ( $column == 'mobile_app' ) {
                $columns['mobile_app']               = 'mobile_app';
                $columns['mobile_app_download_data'] = 'mobile_app_download_data';
            }
            if ( $column == 'randomization' && $data["show"] ) {
                $columns['random_setup']     = 'random_setup';
                $columns['random_dashboard'] = 'random_dashboard';
                $columns['random_perform']   = 'random_perform';
            }
        }

        $rows = array();
        foreach ( $this->permissions["roles"] as $role ) {
            $row = array();
            foreach ( $columns as $column => $csvColumn ) {
                $val = (string) $role[$this->convertRightName($column, false)];
                if ( $csvColumn == 'forms' || $csvColumn == 'forms_export' ) {
                    $val = $this->reformatFormsString($val);
                }
                // if ( str_contains($val, ",") ) {
                //     $val = '"' . $val . '"';
                // }
                $row[] = $val;
            }
            $rows[] = $row;
        }

        return [ 'header' => array_values($columns), 'rows' => $rows ];
    }

    public function createRoleAssignmentsCsv()
    {
        $csvParts = $this->createRoleAssignmentsArray();
        return $this->createCsvFromArray($csvParts);
    }

    public function createRoleAssignmentsArray()
    {
        $header = [ "username", "unique_role_name" ];
        $rows   = array();
        foreach ( $this->permissions["users"] as $user ) {
            $row            = array();
            $row[]          = $user["username"];
            $roleId         = $user["role_id"];
            $roleAssignment = $this->permissions["roles"][$roleId];
            if ( !empty($roleAssignment) ) {
                $row[] = $roleAssignment["unique_role_name"];
            } else {
                $row[] = '';
            }
            $rows[] = $row;
        }
        return [ 'header' => $header, 'rows' => $rows ];
    }

    /**
     * Creates a CSV string from an array of CSV parts
     * @param array $csvParts associative array with 'header' and 'rows' keys
     * @return string CSV string
     */
    public function createCsvFromArray(array $csvParts)
    {
        $header = implode(",", $csvParts['header']);
        $rows   = array();
        foreach ( $csvParts['rows'] as $row ) {
            $rows[] = implode(",", $row);
        }
        $csv = $header . "\n" . implode("\n", $rows);
        return $csv;
    }

    public function createZipArchive($filename)
    {
        $userCsv            = $this->createUsersCsv();
        $roleCsv            = $this->createRolesCsv();
        $roleAssignmentsCsv = $this->createRoleAssignmentsCsv();

        // $firstUser = reset($this->permissions['users']);
        // $filename  = 'user_rights_history_PID' . $firstUser['project_id'] . '_' . $this->permissions['timestamp'] . '.zip';

        $zip = new \ZipArchive;
        $res = $zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ( $res === TRUE ) {
            $zip->addFromString('users.csv', $userCsv);
            $zip->addFromString('roles.csv', $roleCsv);
            $zip->addFromString('role_assignments.csv', $roleAssignmentsCsv);
            return $zip->close();
        } else {
            throw new \Exception("Could not create zip archive");
        }
    }
}