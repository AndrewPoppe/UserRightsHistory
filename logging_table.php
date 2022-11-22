<?php
$module->showPageHeader("logging_table", $description);
$json = json_encode(json_decode('{"post_award_administration":{"id":2,"title":"Post-Award Administration","survey_id":null}}'), JSON_PRETTY_PRINT);
echo "<pre>";
$logs = $module->getLogs();
echo "</pre>";

?>
<p>
    This page
    <br>
    ...
</p>
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
