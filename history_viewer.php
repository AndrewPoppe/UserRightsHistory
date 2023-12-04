<?php
namespace YaleREDCap\UserRightsHistory;

?>
<div class="module-container">
    <?php
    $module->initializeJavascriptModuleObject();
    $module->showPageHeader("history_viewer", $description);

    if ( isset($_GET["datetime"]) ) {
        $timestamp = intval($_GET["datetime"]);
    } else {
        $timestamp = microtime(true) * 1000;
    }

    // Get User's Date Format
    $date_format     = \DateTimeRC::get_user_format_php();
    $time_format     = explode("_", \DateTimeRC::get_user_format_full(), 2)[1];
    $datetime_format = $date_format . " " . ($time_format == 24 ? "H:i" : "h:i K");

    ?>
    <p>
        This page may be used for investigating which users had access to this project and what permissions those users
        had.
        <br>
        You may select a date and time, and the user rights at that point in time will be displayed below. You can only
        select dates
        <br>
        following the moment this module was installed in the project.
    </p>
    <link
        href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.8/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/fc-4.3.0/datatables.min.css"
        rel="stylesheet">
    <script
        src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.8/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/fc-4.3.0/datatables.min.js"></script>
    <script type="text/javascript">
        const module = <?= $module->getJavascriptModuleObjectName() ?>;
        var projectStatus;
        var newDate = new Date(<?= $timestamp ?>);
        $(function () {
            $('#datetime_icon').attr('src', app_path_images + 'date.png');
            const fp = $('#datetime').flatpickr({
                onClose: function () {
                    pageLoad()
                },
                enableTime: true,
                allowInput: true,
                defaultDate: newDate,
                dateFormat: "<?= $datetime_format ?>",
                minDate: new Date(<?= $module->getEarliestLogTimestamp() ?>),
                maxDate: Date.now(),
                time_24hr: <?= $time_format == 24 ? "true" : "false" ?>
            });

            let myDefaultWhiteList = $.fn.popover.Constructor.Default.whiteList ?? $.fn.popover.Constructor.Default.allowList;
            myDefaultWhiteList.table = ["style", "class"];
            myDefaultWhiteList.tr = ["class"];
            myDefaultWhiteList.th = ["style"];
            myDefaultWhiteList.td = [];
            myDefaultWhiteList.input = ["type", "checked"];
            myDefaultWhiteList.thead = [];
            myDefaultWhiteList.tbody = [];
            myDefaultWhiteList.span = ["style"];
            myDefaultWhiteList.i = ["style", "class"];


            $('[data-toggle="popover"]').popover({
                html: true,
                container: 'body',
                placement: 'right',
                boundary: 'viewport',
                whiteList: myDefaultWhiteList
            });

            const table = $('table#userrights').DataTable({
                paging: false,
                scrollX: true,
                fixedColumns: {
                    left: 2
                },
                stateSave: false,
                ordering: false,
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Export to Excel',
                        className: 'btn btn-sm btn-success',
                        className: 'btn btn-success btn-xs mb-1',
                        init: function (api, node, config) {
                            $(node).removeClass('dt-button');
                        },
                        filename: `UserRightsHistory_PID${pid}_${moment(newDate).format()}`,
                        exportOptions: {
                            format: {
                                header: function (data, columnIdx, node) {
                                    return $('<textarea>').html(data).text().trim();
                                },
                                body: function (data, ri, ci, node) {
                                    if (data.includes('tick.png')) {
                                        let result = 'Yes';
                                        if (data.includes('Download all data')) {
                                            result += ' (Download all data)';
                                        }
                                        return result;
                                    } else if (data.includes('cross.png')) {
                                        return 'No';
                                    } else if (data.includes('tick_shield.png')) {
                                        return 'Yes (with E-signature authority)';
                                    } else if (data.includes('<div class="userRightsTableForms">')) {
                                        return $(data.replaceAll('<div class="userRightsTableForms">', '<div class="userRightsTableForms">NEWLINE')).text().replaceAll('NEWLINE', '\n').trim()
                                    } else if (data.includes('<hr>')) {
                                        return $(data.replaceAll('<hr>', '<span>NEWLINE</span>')).text().replaceAll('NEWLINE', '\n\n').trim();
                                    } else if (/\bImport\b|\bExport\b/.test(data)) {
                                        return $(data).text().replaceAll('Import', '\nImport').replaceAll('Export', '\nExport').trim();
                                    } else {
                                        return $(data).text().trim();
                                    }
                                }
                            }
                        },
                        customize: function (xlsx, button, api) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            $('row:not([r="2"]) c', sheet).attr('s', '55');
                            const projectInfo = `PID: ${pid} - ${projectStatus} - ${newDate.toLocaleString()}`;
                            $('row[r="1"] t', sheet).text(projectInfo);
                            $('row[r="1"] c', sheet).attr('s', '32');
                        }
                    }
                ],
                dom: "Bt",
                "order": [
                    [0, 'asc'],
                    [1, 'asc']
                ],
                columnDefs: [{
                    "targets": 0,
                    "data": function (row, type, val, meta) {
                        if (type === "set") {
                            row.orig = val;
                            row.text = strip(val);
                        } else if (type === "display") {
                            return row.orig;
                        } else if (type === "sort") {
                            return row.text.replace('—', '');
                        }
                        return row.orig;
                    }
                }],
                scrollY: 'calc(100vh - 220px)',
                scrollCollapse: true,
                initComplete: function () {
                    $('.spinner-container').hide();
                    $('table').css('opacity', 1);
                }
            });

            table.on('draw', function () {
                $('.dataTable tbody tr').each((i, row) => {
                    row.onmouseenter = hover;
                    row.onmouseleave = dehover;
                });
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

        function pageLoad(event) {
            if (event != null && event.keyCode != 13) {
                return;
            }
            showProgress(1);
            const datetime = $('#datetime').val() === '' ? Date.now() : document.querySelector("#datetime")._flatpickr.selectedDates[0].getTime();
            window.location.href = `<?= $module->getUrl('history_viewer.php') ?>&datetime=${datetime}`;
        }

        function strip(html) {
            let doc = new DOMParser().parseFromString(html, 'text/html');
            return doc.body.textContent || "";
        }

        function customizeCsv(csv) {
            //Split the csv to get the rows
            const split_csv = csv.split("\n");

            //For each row except the first one (header)
            $.each(split_csv.slice(1), function (index, csv_row) {
                //Split on quotes and comma to get each cell
                let csv_cell_array = csv_row.split('","');

                //Remove replace the two quotes which are left at the beginning and the end (first and last cell)
                csv_cell_array[0] = csv_cell_array[0].replace(/"/g, '');
                csv_cell_array[5] = csv_cell_array[5].replace(/"/g, '');

                csv_cell_array = csv_cell_array.map(function (cell) {
                    const doc = new DOMParser().parseFromString(cell, 'text/html');
                    return doc.body.textContent || "";
                });

                //Join the table on the quotes and comma; add back the quotes at the beginning and end
                csv_cell_array_quotes = '"' + csv_cell_array.join('","') + '"';

                //Insert the new row into the rows array at the previous index (index +1 because the header was sliced)
                split_csv[index + 1] = csv_cell_array_quotes;
            });

            //Join the rows with line breck and return the final csv (datatables will take the returned csv and process it)
            csv = split_csv.join("\n");
            return csv;

        }
    </script>
    <div
        style="margin:20px 0px;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:630px;">
        <div style="color:#444;"><span style="color:#000;font-weight:bold;font-size:13px;margin-right:5px;">Choose a
                date and time:</span> The user rights at that point in time will be displayed below.</div>
        <div style="margin:8px 0 0 0px;">
            <input id="datetime">&nbsp;
            <img id="datetime_icon" onclick="document.querySelector('#datetime')._flatpickr.toggle();"
                style="cursor:pointer">
        </div>
        <div id="warning" style="margin-top: 5px;">
            <?= $module->shouldDagsBeChecked() ? "<span style='color:#C00000; margin-bottom: 5px;'>Note: Since you have been assigned to a Data Access Group, you are only able to view users from your group.</span><br>" : "" ?>
        </div>
    </div>
    <?php
    $permissions = $module->getAllInfoByTimestamp($timestamp);
    $module->renderTable($permissions);
    $renderer = new Renderer($permissions);
    $status   = $renderer->getProjectStatus();
    ?>
    <script>
        projectStatus = $(`<?= $status ?>`).text();
    </script>
</div>