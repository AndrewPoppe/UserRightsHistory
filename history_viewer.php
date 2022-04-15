<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/jquery-ui.min.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/jquery-ui-timepicker-addon.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('lib/datatables.min.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl('userRightsTable.css') ?>" />
<script src="<?= $module->getUrl('lib/jquery-ui.min.js') ?>"></script>
<script src="<?= $module->getUrl('lib/jquery-ui-timepicker-addon.js') ?>"></script>
<script src="<?= $module->getUrl('lib/datatables.min.js') ?>"></script>
<script type="text/javascript">
    $(function() {
        console.log(<?= $module->getEarliestLogTimestamp() ?>)
        console.log(new Date(<?= $module->getEarliestLogTimestamp() ?>))
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
            minDateTime: new Date(<?= $module->getEarliestLogTimestamp() ?>),
            constrainInput: false
        });

        let myDefaultWhiteList = $.fn.popover.Constructor.Default.whiteList;
        myDefaultWhiteList.table = ["style", "class"];
        myDefaultWhiteList.tr = ["class"];
        myDefaultWhiteList.th = ["style"];
        myDefaultWhiteList.td = [];
        myDefaultWhiteList.input = ["type", "checked"];
        myDefaultWhiteList.thead = [];
        myDefaultWhiteList.tbody = [];
        myDefaultWhiteList.span = ["style"];


        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'hover',
            container: 'body',
            //placement: 'right',
            whiteList: myDefaultWhiteList
        });

        const table = $('table#userrights').DataTable({
            paging: false,
            buttons: ['colvis'],
            scrollX: true,
            fixedColumns: {
                left: 2
            },
            stateSave: true,
            dom: "t",
            "order": [
                [0, 'desc']
            ],
            columnDefs: [{
                "targets": 0,
                "data": function(row, type, val, meta) {
                    if (type === "set") {
                        row.orig = val;
                        row.text = strip(val);
                    } else if (type === "display") {
                        return row.orig;
                    }
                    return row.orig;
                }
            }]
        });

        table.on('draw', function() {
            $('.dataTable tbody tr').each((i, row) => {
                row.onmouseenter = hover;
                row.onmouseleave = dehover;
            });
        });
        table.rows().every(function() {
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
        const datetime = $('#datetime').val() === '' ? Date.now() : new Date($('#datetime').datetimepicker('getDate')).getTime();
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
        $.each(split_csv.slice(1), function(index, csv_row) {
            //Split on quotes and comma to get each cell
            let csv_cell_array = csv_row.split('","');

            //Remove replace the two quotes which are left at the beginning and the end (first and last cell)
            csv_cell_array[0] = csv_cell_array[0].replace(/"/g, '');
            csv_cell_array[5] = csv_cell_array[5].replace(/"/g, '');

            csv_cell_array = csv_cell_array.map(function(cell) {
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
<div class="projhdr">
    <div style="float:left;">
        <i class="fas fa-history"></i>
        User Rights History
    </div>
    <br>
</div>
<p>
    This page may be used for investigating which users had access to this project and what permissions those users had.
    You may select a date and time, and the user rights at that point in time will be displayed below. You can only
</p>
<div style=" margin:20px 0;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:630px;">
    <div style="color:#444;"><span style="color:#000;font-weight:bold;font-size:13px;margin-right:5px;">Choose a date and time:</span> The user rights at that point in time will be displayed below.</div>
    <div style="margin:8px 0 0 0px;">
        <input id="datetime" onclick="$(this).next('img').click();">
    </div>
</div>
<?php
if (isset($_GET["datetime"])) {
    $timestamp = intval($_GET["datetime"]);
} else {
    $timestamp = microtime(true) * 1000;
}
echo "<script>$(function() {const newDate = new Date($timestamp);$('#datetime').datetimepicker('setDate', newDate);});</script>";

$permissions = $module->getAllInfoByTimestamp($timestamp);
$module->renderTable($permissions);
