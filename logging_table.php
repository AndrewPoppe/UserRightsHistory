<div class="module-container">
    <?php
    if ( $module->getProjectSetting("disable-logging-table") == "1" ) {
        header("Location: " . $module->getUrl("history_viewer.php"));
        exit();
    }
    $module->showPageHeader("logging_table");
    $module->initializeJavascriptModuleObject();

    // If user is in a DAG and settings are appropriate, don't show the logging table.
    $current_dag                   = $module->getCurrentDag($module->getProject()->getProjectId(), $module->getUser()->getUsername());
    $prevent_dags_from_seeing_logs = $module->getProjectSetting("prevent_logs_for_dags");
    if ( $current_dag != null && $prevent_dags_from_seeing_logs != "1" ) {
        echo "<span style='color:#C00000; margin-bottom: 5px;'>Since you have been assigned to a Data Access Group, you are not able to view the User Rights History logs.</span><br>";
        exit();
    }

    // Get User's Date Format
    $date_format     = \DateTimeRC::get_user_format_php();
    $time_format     = explode("_", \DateTimeRC::get_user_format_full(), 2)[1];
    $datetime_format = $date_format . " " . ($time_format == 24 ? "H:i:S" : "h:i:S K");

    [ $initialLogs, $recordsFiltered ] = $module->getLogs([
        "start"   => 0,
        "length"  => 10,
        "search"  => [
            "value" => "",
            "regex" => false
        ],
        "order"   => [
            [
                "column" => 0,
                "dir"    => "desc"
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

    $messages        = [
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
    foreach ( $messages as $message ) {
        $messages_pretty[$message] = ucwords(str_replace('_', ' ', $message));
    }
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
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.css" />
    <script type="text/javascript"
        src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-html5-2.3.3/b-print-2.3.3/date-1.2.0/rg-1.3.0/datatables.min.js"></script>
    <script type="text/javascript">
        const module = <?= $module->getJavascriptModuleObjectName() ?>;
        const totalRecords = "<?= $module->getTotalLogCount() ?>";
        $(document).ready(function () {
            var table = $('#history_logging_table').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: totalRecords,
                searchDelay: 400,
                buttons: [{
                    text: '<i class="fas fa-file-excel"></i> Export to Excel',
                    action: function ( e, dt, node, config ) {
                        var myButton = this;
                        var origLen = dt.page.len();
                        dt.one( 'draw', function () {
                            $.fn.dataTable.ext.buttons.excelHtml5.action.call(myButton, e, dt, node, config);                 
                            dt.page.len(origLen).draw();
                        });
                        dt.page.len(-1).draw();      
                    },
                    className: 'btn btn-success btn-sm mr-2',
                    init: function (api, node, config) {
                        $(node).removeClass('dt-button');
                    },
                    filename:  `UserRightsHistory_PID${pid}_${new Date().toISOString().slice(0, 10)}`
                }],
                dom:'Blfrtip',
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All']
                ],
                scrollX: true,
                scrollCollapse: true,
                ajax: function(data, callback, settings) {
                    const payload = {
                        "draw": data.draw,
                        "search": data.search,
                        "start": data.start,
                        "length": data.length,
                        "order": data.order,
                        "columns": data.columns,
                        "minDate": $('.timestamp.min').val(),
                        "maxDate": $('.timestamp.max').val()
                    };
                    module.ajax('logging_table_ajax', payload)
                    .then(response => {
                        console.log(response);
                        callback(response);
                    })
                    .catch(error => {
                        console.error(error);
                    });
                },
                columns: [{
                    data: 'timestamp',
                    searchable: false,
                    render: function (data, type) {
                        if (type === 'display') {
                            return flatpickr.formatDate(new Date(data), '<?= $datetime_format ?>');
                        }
                        return data;
                    },
                    width: "15%"
                },
                {
                    data: 'message',
                    render: function (data, type) {
                        if (type === 'display') {
                            return data.replace('_', ' ').replace(/\S+/gm, (match, offset) => {
                                return ((offset > 0 ? ' ' : '') + match[0].toUpperCase() + match.substr(1));
                            })
                        }
                        return data;
                    },
                    width: "20%"
                },
                {
                    data: 'previous',
                    width: "32.5%",
                    render: function (data, type) {
                        if (type === 'display') {
                            return syntaxHighlight(data);
                        }
                        return data;
                    }
                },
                {
                    data: 'current',
                    width: "32.5%",
                    render: function (data, type) {
                        if (type === 'display') {
                            return syntaxHighlight(data);
                        }
                        return data;
                    }
                },
                ],
                order: [
                    [0, 'desc']
                ],
                initComplete: function () {
                    // Apply the search
                    let table = this;
                    this.api()
                        .columns()
                        .every(function () {
                            var that = this;
                            $('input', this.header()).not('.timestamp').on('keyup change clear', function () {
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
                        onClose: function () {
                            $('#history_logging_table').DataTable().search('').draw();
                            const fp_max = document.querySelector('input.timestamp.max')._flatpickr;
                            const fp_min = document.querySelector('input.timestamp.min')._flatpickr;
                            fp_max.set('minDate', fp_min.selectedDates[0]);
                        }
                    }));
                    $('input.timestamp.max').flatpickr(Object.assign(fp_opts, {
                        onClose: function () {
                            $('#history_logging_table').DataTable().search('').draw();
                            const fp_max = document.querySelector('input.timestamp.max')._flatpickr;
                            const fp_min = document.querySelector('input.timestamp.min')._flatpickr;
                            fp_min.set('maxDate', fp_max.selectedDates[0]);
                        }
                    }));
                    $('input.timestamp').attr('onclick', "event.stopPropagation();");

                    $('.message-select').on('change', function (event) {
                        $('.message-select').css('color', 'rgb(73, 80, 87)');
                        const searchValue = event.target.value;
                        table.DataTable().column(event.target.parentElement).search(searchValue, true).draw();
                    });

                    table.DataTable().columns.adjust();
                    $('table').css('opacity', 1);
                },
                drawCallback: function (settings) {
                    const table = this.DataTable();
                    table.rows().every(function () {
                        const rowNode = this.node();
                        const rowIndex = this.index();
                        $(rowNode).attr('data-dt-row', rowIndex);
                    });
                    $('.dataTable tbody tr').each((i, row) => {
                        row.onmouseenter = hover;
                        row.onmouseleave = dehover;
                    });
                }
            });

            table.rows().every(function () {
                const rowNode = this.node();
                const rowIndex = this.index();
                $(rowNode).attr('data-dt-row', rowIndex);
            });

            $('.dataTable tbody tr').each((i, row) => {
                row.onmouseenter = hover;
                row.onmouseleave = dehover;
            });


        });

        function hover() {
            const thisNode = $(this);
            const rowIdx = thisNode.attr('data-dt-row');
            $("tr[data-dt-row='" + rowIdx + "'] td").addClass("highlight"); // shade only the hovered row
        }

        function dehover() {
            const thisNode = $(this);
            const rowIdx = thisNode.attr('data-dt-row');
            $("tr[data-dt-row='" + rowIdx + "'] td").removeClass("highlight"); // shade only the hovered row
        }

        function getIndex(str, char, n) {
            return str.split(char).slice(0, n).join(char).length;
        }

        function getClosestIndex(str, char, max_instances, max_chars = 500) {
            let index = Infinity;
            let instance = max_instances;
            let result;
            while (index > max_chars && instance > 0) {
                index = getIndex(str, char, instance--);
            }
            return index;
        }

        function syntaxHighlight(json) {
            if (json === "") json = null;
            json = JSON.stringify(JSON.parse(json), undefined, 4);
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const result = json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                var cls = 'number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'key';
                    } else {
                        cls = 'string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'boolean';
                } else if (/null/.test(match)) {
                    cls = 'null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });

            const lines = result.split("\n").length;
            let final_result;
            if (lines > 10 || result.length > 500) {
                const result_preview = result.substring(0, getClosestIndex(result, '\n', 9));
                const result_tail = result.substring(getClosestIndex(result, '\n', 9));
                final_result = `<pre class="preview" style="margin-bottom:0px !important;padding-bottom:0px !important;">${result_preview}</pre><pre class="break">    ...</pre><pre class="tail" style="display:none; margin-top:0px !important;padding-top:0px !important;">${result_tail}</pre><button type="button" class="btn btn-outline-primary btn-sm" onclick="$(this).siblings('pre.break').toggle();$(this).siblings('pre.tail').slideToggle(500);$(this).text(($(this).text()=='Show More'?'Show Less':'Show More')); $(this).toggleClass('btn-outline-primary').toggleClass('btn-primary');$('#history_logging_table').DataTable().columns.adjust();">Show More</button>`;
            } else {
                final_result = `<pre>${result}</pre>`;
            }
            return final_result;
        }
    </script>
    <p>
        This page shows the changes to the project in a tabular form. This is useful when searching for a particular
        user rights change.
    </p>
    <br>
    <div class="container">
        <div class="options">

        </div>
        <table id="history_logging_table" class="stripe compact cell-border" style="opacity: 0; width: 100%;">
            <thead>
                <tr>
                    <th>
                        Timestamp
                        <br><input onclick="event.stopPropagation();" class="timestamp min form-control-sm" type="text"
                            placeholder="Min Timestamp" />
                        <input onclick="event.stopPropagation();" class="timestamp max form-control-sm" type="text"
                            placeholder="Max Timestamp" />
                    </th>
                    <th>Update Type<br><select class="message-select form-control form-control-sm"
                            onclick="event.stopPropagation();">
                            <option hidden>Select Update Type</option>
                            <option value=""></option>
                            <?php foreach ( $messages_pretty as $value => $name ) { ?>
                                <option class="choice" value="<?= $value ?>">
                                    <?= $name ?>
                                </option>
                            <?php } ?>
                        </select></th>
                    <th>Previous Value<br><input onclick="event.stopPropagation();" class="form-control form-control-sm"
                            type="text" placeholder="Search Previous Value"></th>
                    <th>New Value<br><input onclick="event.stopPropagation();" class="form-control form-control-sm"
                            type="text" placeholder="Search New Value"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $initialLogs as $log ) { ?>
                    <tr>
                        <td>
                            <?= $log["timestamp"] ?>
                        </td>
                        <td>
                            <?= $log["message"] ?>
                        </td>
                        <td>
                            <?= $log["previous"] ?>
                        </td>
                        <td>
                            <?= $log["current"] ?>
                        </td>
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
            white-space: pre-wrap;
        }

        .ui-timepicker-div dl dd {
            margin: 14px 10px 10px 40%;
        }

        div.container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        table.dataTable#history_logging_table {
            border-collapse: collapse !important;
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

        .dataTable thead tr {
            background-color: rgb(220, 220, 220) !important;
        }

        td.highlight {
            background-color: #d9ebf5 !important;
        }
    </style>
</div>