<?php
require 'header.php';  

$result = $mysqli->query("SHOW TABLES");
if (!$result) {
    die("Query failed: " . $mysqli->error);
}

while ($row = $result->fetch_row()) {
    echo $row[0] . "<br>";
}