<?php
$start = time();
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
//$module->showLoggingTable();
//$end = time();
//echo $end - $start;

?>
<script>
    //TODO: Get rid of this nastiness 
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.2/moment.min.js"></script>
<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName() ?>;
    const totalRecords = "<?= $module->getTotalLogCount() ?>";
    const columnSearch = $.fn.dataTable.util.throttle(function(column, searchTerm) {
        column.search(searchTerm).draw();
    }, 400);
    $(document).ready(function() {
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
            //scrollY: 'calc(100vh - ' + $("#history_logging_table").offset().top + 'px - 200px)',
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
                    }
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
                    }
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
                let table = this;
                this.api()
                    .columns()
                    .every(function() {
                        var that = this;
                        $('input', this.header()).not('.timestamp').on('keyup change clear', function() {
                            if (that.search() !== this.value) {
                                columnSearch(that, this.value);
                                //that.search(this.value).draw();
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
                $('input.timestamp').removeClass("form-control").attr('onclick', "event.stopPropagation();");
            },
        });
    });
</script>
<p>
    This page
    <br>
    ...
</p>
<div class="container">
    <div class="options">

    </div>
    <table id="history_logging_table" class="display compact cell-border" style="width: 100%;">
        <thead>
            <tr>
                <th>
                    Timestamp
                    <br><input onclick="event.stopPropagation();" class="timestamp min" type="text" placeholder="Min Timestamp" />
                    <br><input onclick="event.stopPropagation();" class="timestamp max" type="text" placeholder="Max Timestamp" />
                </th>
                <th>Message<br><input onclick="event.stopPropagation();" type="text" placeholder="Search Message"></th>
                <th>Previous Value<br><input onclick="event.stopPropagation();" type="text" placeholder="Search Previous Value"></th>
                <th>New Value<br><input onclick="event.stopPropagation();" type="text" placeholder="Search New Value"></th>
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
        background-color: inherit !important;
        border: none !important;
    }

    .ui-timepicker-div dl dd {
        margin: 14px 10px 10px 40%;
    }

    div.container {
        width: 100%;
    }

    .dataTables_processing {
        z-index: 9000 !important;
    }
</style>
<?php
$end = time();
$module->log('total time', ["time" => $end - $start]);
