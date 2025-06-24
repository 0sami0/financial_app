<?php
function log_error($message) {
    $logFile = __DIR__ . '/logs/error.log';
    $date = date('Y-m-d H:i:s');
    $msg = "[$date] $message" . PHP_EOL;
    file_put_contents($logFile, $msg, FILE_APPEND);
}
?>
