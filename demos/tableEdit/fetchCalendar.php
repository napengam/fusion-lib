<?php

require_once '../../php/Calendar.php';

$json = json_decode(file_get_contents('php://input'), true);
$y = $json['y'];
$m = $json['m'];
$target = $json['target'];
$cal = new calendar('de', ['01.04.2025' => ['april', 'April']]);
$json['result'] = $cal->makeCalendar((int) $m, (int) $y, $target);
echo json_encode($json);
