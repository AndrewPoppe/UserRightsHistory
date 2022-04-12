<input id="datetime" onclick="$(this).next('img').click();">
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/jquery-ui.min.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/jquery-ui-timepicker-addon.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/datatables.min.css') ?>" />
<script src="<?= $module->getUrl('lib/jquery-ui.min.js') ?>"></script>
<script src="<?= $module->getUrl('lib/jquery-ui-timepicker-addon.js') ?>"></script>
<script src="<?= $module->getUrl('lib/datatables.min.js') ?>"></script>
<script type="text/javascript">
    $(function() {
        $('#datetime').datetimepicker({
            onClose: function() {
                pageLoad()
            },
            yearRange: '-100:+0',
            changeMonth: true,
            changeYear: true,
            dateFormat: user_date_format_jquery,
            hour: currentTime('h'),
            minute: currentTime('m'),
            buttonText: 'Click to select a date/time',
            showOn: 'button',
            buttonImage: app_path_images + 'date.png',
            buttonImageOnly: true,
            timeFormat: 'hh:mm tt',
            maxDate: new Date(),
            constrainInput: false
        });

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'hover',
            container: 'body',
            placement: 'right'
        });

        $('table').DataTable({

        });
    });

    function pageLoad(event) {
        if (event != null && event.keyCode != 13) {
            return;
        }
        showProgress(1);
        const datetime = $('#datetime').val() === '' ? Date.now() : new Date($('#datetime').datetimepicker('getDate')).getTime();
        window.location.href = `<?= $module->getUrl('history_viewer.php') ?>&datetime=${datetime}`;
    }
</script>
<style>
    span.popoverspan {
        cursor: help;
    }

    table,
    table p {
        font-size: 12px;
    }

    tbody tr:hover {
        background-color: #d9ebf5 !important;
    }

    tbody td {
        background: transparent !important;
    }

    tr.even {
        background-color: #f3f3f3 !important;
    }

    tr.odd {
        background-color: white !important;
    }

    thead th {
        background-color: #ececec !important;
    }

    hr {
        height: 1px;
        border-width: 0;
        color: #eee;
        background-color: #eee
    }
</style>

<?php

if (isset($_GET["datetime"])) {
    $timestamp = intval($_GET["datetime"]);
    echo "<script>$(function() {const newDate = new Date($timestamp);$('#datetime').datetimepicker('setDate', newDate);});</script>";

    $permissions = $module->getAllInfoByTimestamp($timestamp);
    $module->renderTable($permissions);
}
