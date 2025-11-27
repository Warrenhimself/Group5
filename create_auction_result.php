<?php
require 'header.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'seller') {
  header('Location: browse.php');
  exit();
}

$seller_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: create_auction.php');
  exit();
}

$title         = trim($_POST['title']        ?? '');
$description   = trim($_POST['description']  ?? '');
$category_id   = $_POST['category']          ?? '';
$start_price   = $_POST['start_price']       ?? '';
$reserve_price = $_POST['reserve_price']     ?? '';
$end_time_raw  = $_POST['end_time']          ?? '';

$errors = [];

if ($title === '')        $errors[] = "Title is required.";
if ($description === '')  $errors[] = "Description is required.";
if ($category_id === '')  $errors[] = "Category is required.";
if ($start_price === '' || $start_price < 0) $errors[] = "Start price must be non-negative.";
if ($end_time_raw === '') $errors[] = "End time is required.";

if ($end_time_raw !== '') {
  $end_time = date('Y-m-d H:i:s', strtotime($end_time_raw));
} else {
  $end_time = null;
}

if (!empty($errors)) {
  echo '<div class="container mt-4"><div class="alert alert-danger"><ul>';
  foreach ($errors as $e) {
    echo '<li>' . htmlspecialchars($e) . '</li>';
  }
  echo '</ul><a href="create_auction.php" class="btn btn-secondary mt-2">Back</a></div></div>';
  include 'footer.php';
  exit();
}

$stmt = $mysqli->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
$stmt->bind_param("i", $seller_user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  $stmt->close();
  $ins = $mysqli->prepare("INSERT INTO sellers (user_id) VALUES (?)");
  $ins->bind_param("i", $seller_user_id);
  $ins->execute();
  $seller_id = $ins->insert_id;
  $ins->close();
} else {
  $row = $res->fetch_assoc();
  $seller_id = $row['seller_id'];
  $stmt->close();
}

$condition = 'New';

$item_sql = "INSERT INTO items (title, description, category_id, seller_id, `condition`) VALUES (?, ?, ?, ?, ?)";
$item_stmt = $mysqli->prepare($item_sql);
$item_stmt->bind_param("ssiss", $title, $description, $category_id, $seller_id, $condition);

if (!$item_stmt->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Failed to create item: ' . htmlspecialchars($item_stmt->error) . '</div></div>';
  $item_stmt->close();
  include 'footer.php';
  exit();
}
$item_id = $mysqli->insert_id;
$item_stmt->close();

if ($reserve_price === '' || $reserve_price === null) {
  $reserve_price = null;
}

$auction_sql = "INSERT INTO auctions (item_id, start_price, reserve_price, start_time, end_time, status, currentHighestBid, winnerId) VALUES (?, ?, ?, NOW(), ?, 'active', NULL, NULL)";
$auction_stmt = $mysqli->prepare($auction_sql);
$auction_stmt->bind_param("idds", $item_id, $start_price, $reserve_price, $end_time);

if (!$auction_stmt->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Failed to create auction: ' . htmlspecialchars($auction_stmt->error) . '</div></div>';
  $auction_stmt->close();
  include 'footer.php';
  exit();
}
$auction_id = $mysqli->insert_id;
$auction_stmt->close();

echo '<div class="container mt-4">
        <div class="alert alert-success">
          Auction created successfully!
        </div>
        <a href="browse.php" class="btn btn-primary">Back to browse</a>
      </div>';

include 'footer.php';
?>
