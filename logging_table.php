<?php
$start = time();
$module->showPageHeader("logging_table", $description);
$module->initializeJavascriptModuleObject();
$initialLogs = $module->getLogs(["start" => 0, "length" => 10]);
//$module->showLoggingTable();
//$end = time();
//echo $end - $start;

?>
<form style="display:none;"></form>
<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName() ?>;
    const totalRecords = "<?= $module->getTotalLogCount() ?>";
    $(document).ready(function() {
        const token = $('input[name="redcap_csrf_token"]').val();
        $('#history_logging_table').DataTable({
            processing: true,
            serverSide: true,
            deferLoading: totalRecords,
            ajax: {
                url: module.getUrl('logging_table_ajax.php'),
                //type: 'POST',
                // redcap_csrf_token: token,
                token: token,
                data: {
                    token2: token
                }
            },
            columns: [{
                    data: 'timestamp'
                },
                {
                    data: 'message'
                },
                {
                    data: 'previous_formatted'
                },
                {
                    data: 'current_formatted'
                },
            ],
        });
    });
</script>
<p>
    This page
    <br>
    ...
</p>
<table id="history_logging_table">
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Message</th>
            <th>Previous Value</th>
            <th>New Value</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($initialLogs as $log) { ?>
            <tr>
                <td><?= $log["timestamp"] ?></td>
                <td><?= $log["message"] ?></td>
                <td><?= $log["previous_formatted"] ?></td>
                <td><?= $log["current_formatted"] ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>
<style>
    .string {
        color: green;
    }

    .number {
        color: darkorange;
    }

    .boolean {
        color: blue;
    }

    .null {
        color: magenta;
    }

    .key {
        color: darkred;
    }
</style>
<?php
$end = time();
$module->log('total time', ["time" => $end - $start]);
