<?php

// TODO: Implement some kind of authentication - based on User Rights permissions?

$token = $module->getCSRFToken();

//$module->log('test', array('post' => json_encode($_POST), 'get' => json_encode($_GET), 'request' => json_encode($_REQUEST)));

$user = $module->getUser();
$username = $user->getUsername();

$logs = $module->getLogs($_GET);
$total = $module->getTotalLogCount();
$module->log('logtotal', ["total" => $total]);

//$module->log('logs', array('logs' => json_encode($logs)));

$response = array(
    "data" => $logs,
    "draw" => $_GET["draw"],
    "recordsTotal" => $total,
    "recordsFiltered" => $total
);

echo json_encode($response);
