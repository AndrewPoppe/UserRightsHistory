<?php

// TODO: Implement some kind of authentication - based on User Rights permissions?

$params = [
    "search" => filter_input(INPUT_GET, 'search', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
    "start" => filter_input(INPUT_GET, "start", FILTER_VALIDATE_INT),
    "length" => filter_input(INPUT_GET, "length", FILTER_VALIDATE_INT),
    "order" => filter_input(INPUT_GET, "order", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
    "columns" => filter_input(INPUT_GET, "columns", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY)
];

$module->log('params', ['params' => json_encode($params)]);

[$logs, $recordsFiltered] = $module->getLogs($params);
$total = $module->getTotalLogCount();

$response = array(
    "data" => $logs,
    "draw" => filter_input(INPUT_GET, "draw", FILTER_VALIDATE_INT),
    "recordsTotal" => $total,
    "recordsFiltered" => $recordsFiltered
);

echo json_encode($response);
