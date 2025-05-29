<?php
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the IP is actually banned
if (!isset($_SESSION['banned_ips'][$ip]) || time() >= $_SESSION['banned_ips'][$ip]) {
    header("Location: register.php");
    exit;
}

// Calculate remaining time
$remaining_time = $_SESSION['banned_ips'][$ip] - time();
$minutes = floor($remaining_time / 60);
$seconds = $remaining_time % 60;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/ban.css">
    <title>⛔ Banned</title>
</head>
<body>
    <h2>⛔ You are temporarily banned</h2>
    <p>You failed the anti-bot test 3 times.</p>
    <p>Time remaining before you can try again: <strong><?= $minutes ?> min <?= $seconds ?> s</strong></p>
    <p>💡  use a new identity.</p>
</body>
</html>
