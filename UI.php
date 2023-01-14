<?php

namespace YaleREDCap\UserRightsHistory;

class UI
{

    public static $module;
    function __construct($module)
    {
        self::$module = $module;
    }

    public function showPageHeader(string $page)
    {
        echo ($page == "logging_table" ? "" : '<link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('lib/datatables.min.css') . '" />') . '
            <link rel="stylesheet" type="text/css" href="' . self::$module->getUrl('userRightsTable.css') . '" />
            ' . ($page == "logging_table" ? "" : '<script src="' . self::$module->getUrl('lib/datatables.min.js') . '"></script>') . '
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>            
    <div class="projhdr">
        <div style="float:left;">
            <i class="fas fa-history"></i>
            User Rights History
        </div>
        <br>
    </div>

    <div id="sub-nav" class="d-none d-sm-block" style="margin:5px 20px 15px 0px;">
        <ul>
            <li class="' . ($page === "history_viewer" ? "active" : "") . '">
                <a href="' . self::$module->getUrl('history_viewer.php') . '" style="font-size:13px;color:#393733;padding:7px 9px;">
                    <i class="fas fa-history"></i>
                    History Viewer
                </a>
            </li>
            <li class="' . ($page === "logging_table" ? "active" : "") . '">
                <a href="' . self::$module->getUrl('logging_table.php') . '" style="font-size:13px;color:#393733;padding:7px 9px;">
                    <i class="fas fa-receipt"></i>
                    Logs
                </a>
            </li>
        </ul>
    </div>
    <div class="clear"></div>';
    }
}
