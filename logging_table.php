<?php
$start = time();
$module->showPageHeader("logging_table");
$module->initializeJavascriptModuleObject();
[$initialLogs, $recordsFiltered] = $module->getLogs([
    "start" => 0,
    "length" => 10,
    "search" => [
        "value" => "",
        "regex" => false
    ],
    "order" => [
        [
            "column" => 0,
            "dir" => "desc"
        ]
    ],
    "columns" => [
        [
            "data" => "timestamp"
        ]
    ]
]);

$event_types = [
    "Initialize URH Module",
    "Update Project",
    "Added User(s)",
    "Removed User(s)",
    "Added User(s) and Removed User(s)"
];
//$module->showLoggingTable();
//$end = time();
//echo $end - $start;

?>
<script>
    Array.from(document.styleSheets).forEach(ss => {
        try {
            if (!ss.href.includes('https://cdn.datatables.net')) {
                return Array.from(ss.cssRules).forEach(rule => {
                    if (rule.cssText.toLowerCase().includes('datatable')) {
                        rule.style.removeProperty('all')
                    }
                })
            }
        } catch (err) {

        }
    })
</script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.css" />
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.js"></script>
<form style="display:none;"></form>
<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName() ?>;
    const totalRecords = "<?= $module->getTotalLogCount() ?>";
    $(document).ready(function() {
        const token = $('input[name="redcap_csrf_token"]').val();
        $('#history_logging_table tfoot th').each(function() {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');
        });
        $('#history_logging_table').DataTable({
            processing: true,
            serverSide: true,
            deferLoading: totalRecords,
            ajax: {
                url: module.getUrl('logging_table_ajax.php'),
            },
            columns: [{
                    data: 'timestamp'
                },
                {
                    data: 'message'
                },
                {
                    data: 'previous'
                },
                {
                    data: 'current'
                },
            ],
            order: [
                [0, 'desc']
            ],
            initComplete: function() {
                // Apply the search
                this.api()
                    .columns()
                    .every(function() {
                        var that = this;
                        $('input', this.footer()).on('keyup change clear', function() {
                            if (that.search() !== this.value) {
                                that.search(this.value).draw();
                            }
                        });
                    });
            },
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
                <td><?= $log["previous"] ?></td>
                <td><?= $log["current"] ?></td>
            </tr>
        <?php } ?>
    </tbody>
    <tfoot>
        <tr>
            <th>Timestamp</th>
            <th>Message</th>
            <th>Previous Value</th>
            <th>New Value</th>
        </tr>
    </tfoot>
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

    tr.odd {
        background-color: transparent !important;
    }

    pre {
        background-color: inherit !important;
        border: none !important;
    }
</style>
<?php
$end = time();
$module->log('total time', ["time" => $end - $start]);
