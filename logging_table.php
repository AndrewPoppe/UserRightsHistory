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
    "Add User(s)",
    "Remove User(s)",
    "Add User(s) and Remove User(s)"
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.2/moment.min.js"></script>
<!--<form style="display:none;"></form>-->
<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName() ?>;
    const totalRecords = "<?= $module->getTotalLogCount() ?>";
    const columnSearch = $.fn.dataTable.util.throttle(function(column, searchTerm) {
        column.search(searchTerm).draw();
    }, 400);
    $.fn.dataTable.pipeline = function(opts) {
        // Configuration options
        var conf = $.extend({
                pages: 5, // number of pages to cache
                url: '', // script url
                data: null, // function or object with parameters to send to the server
                // matching how `ajax.data` works in DataTables
                method: 'POST', // Ajax HTTP method
            },
            opts
        );

        // Private variables for storing the cache
        var cacheLower = -1;
        var cacheUpper = null;
        var cacheLastRequest = null;
        var cacheLastJson = null;

        return function(request, drawCallback, settings) {
            var ajax = false;
            var requestStart = request.start;
            var drawStart = request.start;
            var requestLength = request.length;
            var requestEnd = requestStart + requestLength;

            if (settings.clearCache) {
                // API requested that the cache be cleared
                ajax = true;
                settings.clearCache = false;
            } else if (cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper) {
                // outside cached data - need to make a request
                ajax = true;
            } else if (
                JSON.stringify(request.order) !== JSON.stringify(cacheLastRequest.order) ||
                JSON.stringify(request.columns) !== JSON.stringify(cacheLastRequest.columns) ||
                JSON.stringify(request.search) !== JSON.stringify(cacheLastRequest.search)
            ) {
                // properties changed (ordering, columns, searching)
                ajax = true;
            }

            // Store the request for checking next time around
            cacheLastRequest = $.extend(true, {}, request);

            if (ajax) {
                // Need data from the server
                if (requestStart < cacheLower) {
                    requestStart = requestStart - requestLength * (conf.pages - 1);

                    if (requestStart < 0) {
                        requestStart = 0;
                    }
                }

                cacheLower = requestStart;
                cacheUpper = requestStart + requestLength * conf.pages;

                request.start = requestStart;
                request.length = requestLength * conf.pages;

                // Provide the same `data` options as DataTables.
                if (typeof conf.data === 'function') {
                    // As a function it is executed with the data object as an arg
                    // for manipulation. If an object is returned, it is used as the
                    // data object to submit
                    var d = conf.data(request);
                    if (d) {
                        $.extend(request, d);
                    }
                } else if ($.isPlainObject(conf.data)) {
                    // As an object, the data given extends the default
                    $.extend(request, conf.data);
                }

                return $.ajax({
                    type: conf.method,
                    url: conf.url,
                    data: request,
                    dataType: 'json',
                    cache: false,
                    success: function(json) {
                        cacheLastJson = $.extend(true, {}, json);

                        if (cacheLower != drawStart) {
                            json.data.splice(0, drawStart - cacheLower);
                        }
                        if (requestLength >= -1) {
                            json.data.splice(requestLength, json.data.length);
                        }

                        drawCallback(json);
                    },
                });
            } else {
                json = $.extend(true, {}, cacheLastJson);
                json.draw = request.draw; // Update the echo for each response
                json.data.splice(0, requestStart - cacheLower);
                json.data.splice(requestLength, json.data.length);

                drawCallback(json);
            }
        };
    };

    // Register an API method that will empty the pipelined data, forcing an Ajax
    // fetch on the next draw (i.e. `table.clearPipeline().draw()`)
    $.fn.dataTable.Api.register('clearPipeline()', function() {
        return this.iterator('table', function(settings) {
            settings.clearCache = true;
        });
    });
    $(document).ready(function() {
        DataTable.datetime('YYYY-MM-DD HH:mm:ss');
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
            ajax: $.fn.dataTable.pipeline({
                url: module.getUrl('logging_table_ajax.php'),
                type: 'POST',
                data: function(dtParams) {
                    dtParams.minDate = $('.timestamp.min').val();
                    dtParams.maxDate = $('.timestamp.max').val();
                    return dtParams;
                }
            }),
            columns: [{
                    data: 'timestamp',
                    searchable: false
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
                $.timepicker.datetimeRange(
                    $('input.timestamp.min'),
                    $('input.timestamp.max'), {
                        changeMonth: true,
                        changeYear: true,
                        yearRange: '-100:+100',
                        dateFormat: 'yy-mm-dd',
                        timeFormat: 'HH:mm:ss',
                        minInterval: 0,
                        onClose: function() {
                            $('#history_logging_table').DataTable().search('').draw();
                        }
                    }
                )
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
        <input class="timestamp min" type="text" placeholder="Min Timestamp" /><br>
        <input class="timestamp max" type="text" placeholder="Max Timestamp" />
    </div>
    <table id="history_logging_table" class="display compact cell-border" style="width: 100%;">
        <thead>
            <tr>
                <th>Timestamp</th>
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
        <tfoot>
            <tr>
                <th>Timestamp</th>
                <th>Message</th>
                <th>Previous Value</th>
                <th>New Value</th>
            </tr>
        </tfoot>
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
        width: 80%;
    }

    .dataTables_processing {
        z-index: 9000 !important;
    }
</style>
<?php
$end = time();
$module->log('total time', ["time" => $end - $start]);
