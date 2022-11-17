<?php

namespace YaleREDCap\UserRightsHistory;

class UI
{

    public static $module;
    function __construct($module)
    {
        self::$module = $module;
    }

    public function showPageHeader(string $page, string $description)
    {
        echo '<link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('lib/jquery-ui.min.css') . '" />
                <link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('lib/jquery-ui-timepicker-addon.css') . '" />
                <link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('lib/datatables.min.css') . '" />
                <link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('userRightsTable.css') . '" />
                <script src="' . self::$module->getUrl('lib/jquery-ui.min.js') . '"></script>
                <script src="' . self::$module->getUrl('lib/jquery-ui-timepicker-addon.js') . '"></script>
                <script src="' . self::$module->getUrl('lib/datatables.min.js') . '"></script>

        <style>
            .urh-nav a {
                color: #303030 !important;
                font-weight: bold !important;
            }

            .urh-nav a.active:hover {
                color: #303030 !important;
                font-weight: bold !important;
                outline: none !important;
            }

            .urh-nav a:hover:not(.active),
            a:focus {
                color: #303030 !important;
                font-weight: bold !important;
                border: 1px solid #c0c0c0 !important;
                background-color: #e1e1e1 !important;
                outline: none !important;
            }

            .urh-nav a:not(.active) {
                background-color: #f7f6f6 !important;
                border: 1px solid #e1e1e1 !important;
                outline: none !important;
            }
        </style>
        <div class="projhdr">
            <div style="float:left;">
                <i class="fas fa-history"></i>
                User Rights History
            </div>
            <br>
        </div>
        <p>
            ' . $description . '
        </p>

        <nav style="margin:5px 0 20px;">
            <ul class="nav nav-tabs urh-nav">
                <li class="nav-item">
                    <a class="nav-link ' . ($page === "history_viewer" ? "active" : "") . '" href="' . self::$module->getUrl('history_viewer.php') . '">History Viewer</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ' . ($page === "logging_table" ? "active" : "") . '" href="' . self::$module->getUrl('logging_table.php') . '">Logs</a>
                </li>
            </ul>
        </nav>
        <div class="clear"></div>';
    }
}
