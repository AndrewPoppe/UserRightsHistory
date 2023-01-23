<?php
$module->showPageHeader("logging_table");
$module->initializeJavascriptModuleObject();

// Get User's Date Format
$date_format = \DateTimeRC::get_user_format_php();
$time_format =  explode("_", \DateTimeRC::get_user_format_full(), 2)[1];
$datetime_format = $date_format . " " . ($time_format == 24 ? "H:i:S" : "h:i:S K");

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

// TODO: use 
$event_types = [
    "Initialize URH Module",
    "Update Project",
    "Add User(s)",
    "Remove User(s)",
    "Add User(s) and Remove User(s)"
];

$messages = [
    'dags',
    'instruments',
    'module enabled by default status',
    'module project status',
    'module system status',
    'module version',
    'project_info',
    'rights',
    'roles',
    'system',
    'users'
];
$messages_pretty = [];
foreach ($messages as $message) {
    $messages_pretty[$message] = ucwords(str_replace('_', ' ', $message));
}
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.css" />
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.js"></script>
<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName() ?>;
    const totalRecords = "<?= $module->getTotalLogCount() ?>";
    $(document).ready(function() {
        console.log(module.getUrl('logging_table_ajax.php'));
        $('#history_logging_table').DataTable({
            processing: true,
            serverSide: true,
            deferLoading: totalRecords,
            searchDelay: 400,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],
            scrollX: true,
            scrollCollapse: true,
            ajax: {
                url: module.getUrl('logging_table_ajax.php'),
                type: 'POST',
                data: function(dtParams) {
                    dtParams.minDate = $('.timestamp.min').val();
                    dtParams.maxDate = $('.timestamp.max').val();
                    return dtParams;
                }
            },
            columns: [{
                    data: 'timestamp',
                    searchable: false,
                    render: function(data, type) {
                        if (type === 'display') {
                            return flatpickr.formatDate(new Date(data), '<?= $datetime_format ?>');
                        }
                        return data;
                    },
                    width: "15%"
                },
                {
                    data: 'message',
                    render: function(data, type) {
                        if (type === 'display') {
                            return data.replace('_', ' ').replace(/\S+/gm, (match, offset) => {
                                return ((offset > 0 ? ' ' : '') + match[0].toUpperCase() + match.substr(1));
                            })
                        }
                        return data;
                    },
                    width: "25%"
                },
                {
                    data: 'previous',
                    width: "30%"
                },
                {
                    data: 'current',
                    width: "30%"
                },
            ],
            order: [
                [0, 'desc']
            ],
            initComplete: function() {
                // Apply the search
                let table = this;
                this.api()
                    .columns()
                    .every(function() {
                        var that = this;
                        $('input', this.header()).not('.timestamp').on('keyup change clear', function() {
                            if (that.search() !== this.value) {
                                that.search(this.value).draw();
                            }
                        });
                    });

                const fp_opts = {
                    enableTime: true,
                    enableSeconds: true,
                    allowInput: true,
                    altInput: true,
                    altFormat: "<?= $datetime_format ?>",
                    dateFormat: "Y-m-d H:i:S",
                    time_24hr: <?= $time_format == 24 ? "true" : "false" ?>
                };
                $('input.timestamp.min').flatpickr(Object.assign(fp_opts, {
                    onClose: function() {
                        $('#history_logging_table').DataTable().search('').draw();
                        const fp_max = document.querySelector('input.timestamp.max')._flatpickr;
                        const fp_min = document.querySelector('input.timestamp.min')._flatpickr;
                        fp_max.set('minDate', fp_min.selectedDates[0]);
                    }
                }));
                $('input.timestamp.max').flatpickr(Object.assign(fp_opts, {
                    onClose: function() {
                        $('#history_logging_table').DataTable().search('').draw();
                        const fp_max = document.querySelector('input.timestamp.max')._flatpickr;
                        const fp_min = document.querySelector('input.timestamp.min')._flatpickr;
                        fp_min.set('maxDate', fp_max.selectedDates[0]);
                    }
                }));
                $('input.timestamp').attr('onclick', "event.stopPropagation();");

                $('.message-select').on('change', function(event) {
                    $('.message-select').css('color', 'rgb(73, 80, 87)');
                    const searchValue = event.target.value;
                    table.DataTable().column(event.target.parentElement).search(searchValue, true).draw();
                });
            },
        });
    });
</script>
<p>
    This page shows the changes to the project in a tabular form. This is useful when searching for a particular user rights change.
</p>
<br>
<div class="container">
    <div class="options">

    </div>
    <table id="history_logging_table" class="display compact cell-border" style="width: 100%;">
        <thead>
            <tr>
                <th>
                    Timestamp
                    <br><input onclick="event.stopPropagation();" class="timestamp min form-control-sm" type="text" placeholder="Min Timestamp" />
                    <input onclick="event.stopPropagation();" class="timestamp max form-control-sm" type="text" placeholder="Max Timestamp" />
                </th>
                <th>Update Type<br><select class="message-select form-control form-control-sm" onclick="event.stopPropagation();">
                        <option hidden>Select Update Type</option>
                        <option value=""></option>
                        <?php foreach ($messages_pretty as $value => $name) { ?>
                            <option class="choice" value="<?= $value ?>"><?= $name ?></option>
                        <?php } ?>
                    </select></th>
                <th>Previous Value<br><input onclick="event.stopPropagation();" class="form-control form-control-sm" type="text" placeholder="Search Previous Value"></th>
                <th>New Value<br><input onclick="event.stopPropagation();" class="form-control form-control-sm" type="text" placeholder="Search New Value"></th>
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
    </table>
</div>
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
        background-color: transparent !important;
        border: none !important;
    }

    .ui-timepicker-div dl dd {
        margin: 14px 10px 10px 40%;
    }

    div.container {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .dataTables_processing {
        z-index: 9000 !important;
    }

    .message-select,
    .message-select:focus {
        color: rgb(187, 187, 187);
    }

    .message-select .choice {
        color: rgb(73, 80, 87) !important;
    }

    th {
        border-top: 1px solid rgba(0, 0, 0, 0.15);
        border-bottom: 1px solid rgba(0, 0, 0, 0.15);
        border-right: 1px solid rgba(0, 0, 0, 0.15);
    }

    table#history_logging_table,
    th:first-child {
        border-left: 1px solid rgba(0, 0, 0, 0.15) !important;
    }

    thead tr {
        background-color: rgb(220, 220, 220) !important;
    }

    td.highlight {
        background-color: #d9ebf5 !important;
    }
</style>
<?php
$end = time();
$module->log('total time', ["time" => $end - $start]);
