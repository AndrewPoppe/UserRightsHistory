<?php
// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<input id="datetime" onclick="$(this).next('img').click();">
<script type="text/javascript">
    $.ui = null;
</script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('jquery-ui.min.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('jquery-ui-timepicker-addon.css') ?>" />
<script src="<?= $module->getUrl('jquery-ui.min.js') ?>"></script>
<script src="<?= $module->getUrl('jquery-ui-timepicker-addon.js') ?>"></script>
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
<?php

if (isset($_GET["datetime"])) {
    $timestamp = intval($_GET["datetime"]);
    $permissions = $module->getAllInfoByTimestamp($timestamp);
    var_dump($timestamp);
    var_dump($permissions);

    $module->renderTable($permissions);

    echo "<script>$(function() {const newDate = new Date($timestamp);$('#datetime').datetimepicker('setDate', newDate);});</script>";
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
