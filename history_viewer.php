<div class="module-container">
    <?php
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
    <script type="text/javascript">
        $(function () {
            $('#datetime_icon').attr('src', app_path_images + 'date.png');
            const newDate = new Date(<?= $timestamp ?>);
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
                //buttons: ['colvis'],
                scrollX: true,
                fixedColumns: {
                    left: 2
                },
                stateSave: false,
                dom: "t",
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
    $result      = $module->query("select external_module_id from redcap_external_modules where directory_prefix = 'user_rights_history'", []);
    $id          = $result->fetch_assoc()["external_module_id"];
    $module->renderTable($permissions);
    ?>
</div>