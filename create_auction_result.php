<?php
include_once("header.php");
require("utilities.php");


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'seller') {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You must be logged in as a seller to create an auction.
        </div></div>';
  include_once("footer.php");
  exit();
}


$title        = trim($_POST['title']        ?? '');
$details      = trim($_POST['description']  ?? '');
$category_id  = isset($_POST['category']) ? (int)$_POST['category'] : null;
$start_price  = ($_POST['start_price']  ?? '') !== '' ? (float)$_POST['start_price']  : null;
$reserve      = ($_POST['reserve_price'] ?? '') !== '' ? (float)$_POST['reserve_price'] : null;
$start_time_in = $_POST['start_time'] ?? null;  
$end_time_in   = $_POST['end_time']   ?? null;  


$errors = [];

if ($title === '')           $errors[] = "Title is required.";
if ($details === '')         $errors[] = "Details are required.";
if (empty($category_id))     $errors[] = "Category is required.";
if ($start_price === null || $start_price < 0)
  $errors[] = "Starting price is required and must be non-negative.";

if ($start_time_in === null || $start_time_in === '') {
  $errors[] = "Start date is required.";
}
if ($end_time_in === null || $end_time_in === '') {
  $errors[] = "End date is required.";
}


$start_time_in = $_POST['start_time'] ?? ''; 
$end_time_in   = $_POST['end_time']   ?? '';

$errors = [];

if ($start_time_in === '') {
    $errors[] = "Start date is required.";
}
if ($end_time_in === '') {
    $errors[] = "End date is required.";
}

if ($start_time_in !== '' && $end_time_in !== '') {

    $start_ts = strtotime($start_time_in);
    $end_ts   = strtotime($end_time_in);
    $now_ts   = time();

    if ($start_ts < $now_ts - 60) {
        $errors[] = "Start date must be in the future.";
    }

    if ($end_ts <= $start_ts) {
        $errors[] = "End date must be after start date.";
    }


    if (empty($errors)) {
        $start_time = date('Y-m-d H:i:s', $start_ts);
        $end_time   = date('Y-m-d H:i:s', $end_ts);
    }
}



$start_time = date('Y-m-d H:i:s', $start_ts);
$end_time   = date('Y-m-d H:i:s', $end_ts);


/** @var mysqli $mysqli */
$user_id = $_SESSION['user_id'];

$sstmt = $mysqli->prepare("SELECT seller_id FROM sellers WHERE user_id = ? LIMIT 1");
$sstmt->bind_param("i", $user_id);
$sstmt->execute();
$ssres = $sstmt->get_result();
if ($ssres->num_rows === 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You are not registered as a seller.
        </div></div>';
  $sstmt->close();
  include_once("footer.php");
  exit();
}
$seller    = $ssres->fetch_assoc();
$seller_id = (int)$seller['seller_id'];
$sstmt->close();


$image_path = null;

if (!empty($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {

  if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Image upload error (code ' . htmlspecialchars($_FILES['item_image']['error']) . ').
          </div></div>';
    include_once("footer.php");
    exit();
  }

  $tmp_name = $_FILES['item_image']['tmp_name'];
  $orig_name = $_FILES['item_image']['name'];

  $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Only JPG, PNG or GIF images are allowed.
          </div></div>';
    include_once("footer.php");
    exit();
  }

  $new_name = 'item_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;

  
  $upload_dir_fs  = __DIR__ . '/img/items/';
 
  $upload_dir_web = 'img/items/';

  if (!is_dir($upload_dir_fs)) {
    mkdir($upload_dir_fs, 0777, true);
  }

  $dest_path_fs  = $upload_dir_fs . $new_name;
  $dest_path_web = $upload_dir_web . $new_name;

  if (!move_uploaded_file($tmp_name, $dest_path_fs)) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Failed to move uploaded image.
          </div></div>';
    include_once("footer.php");
    exit();
  }

  $image_path = $dest_path_web;
}


$condition = 'New';  

$istmt = $mysqli->prepare("
  INSERT INTO items (title, description, category_id, seller_id, `condition`, createdAtDATETIME, image_path)
  VALUES (?, ?, ?, ?, ?, NOW(), ?)
");
$istmt->bind_param("ssiiss", $title, $details, $category_id, $seller_id, $condition, $image_path);

if (!$istmt->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Failed to create item: ' . htmlspecialchars($istmt->error) . '
        </div></div>';
  $istmt->close();
  include_once("footer.php");
  exit();
}
$item_id = $istmt->insert_id;
$istmt->close();

$status            = 'active';
$currentHighestBid = null;

$astmt = $mysqli->prepare("
  INSERT INTO auctions (item_id, start_price, reserve_price, start_time, end_time, status, currentHighestBid)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
$astmt->bind_param("iddsssd", $item_id, $start_price, $reserve, $start_time, $end_time, $status, $currentHighestBid);


if (!$astmt->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Failed to create auction: ' . htmlspecialchars($astmt->error) . '
        </div></div>';
  $astmt->close();
  include_once("footer.php");
  exit();
}
$astmt->close();

echo '<div class="container mt-4">
  <div class="alert alert-success">
    Auction created successfully!
  </div>
  <a href="listing.php?item_id=' . htmlspecialchars($item_id) . '" class="btn btn-primary">View listing</a>
  <a href="mylistings.php" class="btn btn-secondary ml-2">Back to My Listings</a>
</div>';

include_once("footer.php");
?>
