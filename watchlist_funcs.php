<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "not_logged_in";
    exit();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'auctiondb';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    echo "db_error";
    exit();
}

if (!isset($_POST['functionname']) || !isset($_POST['arguments'][0])) {
    echo "bad_request";
    exit();
}

$func       = $_POST['functionname'];
$auction_id = (int)$_POST['arguments'][0];
$user_id    = (int)$_SESSION['user_id'];

if ($auction_id <= 0) {
    echo "bad_auction";
    exit();
}

if ($func === 'add_to_watchlist') {

    $stmt = $mysqli->prepare("
        INSERT IGNORE INTO watchlist (user_id, auction_id, watch_time, notify_enabled, notify_type, message)
        VALUES (?, ?, NOW(), 0, 'none', '')
    ");
    $stmt->bind_param("ii", $user_id, $auction_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "fail";
    }
    $stmt->close();
    exit();

} elseif ($func === 'remove_from_watchlist') {

    $stmt = $mysqli->prepare("DELETE FROM watchlist WHERE user_id = ? AND auction_id = ?");
    $stmt->bind_param("ii", $user_id, $auction_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "fail";
    }
    $stmt->close();
    exit();

} else {
    echo "unknown_function";
    exit();
}
