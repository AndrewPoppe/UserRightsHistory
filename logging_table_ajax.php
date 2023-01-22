<?php

// TODO: Implement some kind of authentication - based on User Rights permissions?

$params = [
    "search" => filter_input(INPUT_POST, "search", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
    "start" => filter_input(INPUT_POST, "start", FILTER_VALIDATE_INT),
    "length" => filter_input(INPUT_POST, "length", FILTER_VALIDATE_INT),
    "order" => filter_input(INPUT_POST, "order", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
    "columns" => filter_input(INPUT_POST, "columns", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
    "minDate" => filter_input(INPUT_POST, "minDate", FILTER_DEFAULT),
    "maxDate" => filter_input(INPUT_POST, "maxDate", FILTER_DEFAULT)
];

$module->log('params', ['params' => json_encode($params), "post" => json_encode($_POST)]);

[$logs, $recordsFiltered] = $module->getLogs($params);
$total = $module->getTotalLogCount();

$response = array(
    "data" => $logs,
    "draw" => filter_input(INPUT_POST, "draw", FILTER_VALIDATE_INT),
    "recordsTotal" => $total,
    "recordsFiltered" => $recordsFiltered
);

echo json_encode($response);
