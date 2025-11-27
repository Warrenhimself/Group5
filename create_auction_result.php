<?php
include_once("header.php");
require("utilities.php");

// 只允许卖家创建拍卖
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'seller') {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You must be logged in as a seller to create an auction.
        </div></div>';
  include_once("footer.php");
  exit();
}

// ---------- 1. 读取表单数据（和 create_auction.php 中 name 对应） ----------
$title        = trim($_POST['title']        ?? '');
$details      = trim($_POST['description']  ?? '');
$category_id  = isset($_POST['category']) ? (int)$_POST['category'] : null;
$start_price  = ($_POST['start_price']  ?? '') !== '' ? (float)$_POST['start_price']  : null;
$reserve      = ($_POST['reserve_price'] ?? '') !== '' ? (float)$_POST['reserve_price'] : null;
$end_time_in  = $_POST['end_time'] ?? null;

// ---------- 2. 简单验证 ----------
$errors = [];

if ($title === '')           $errors[] = "Title is required.";
if ($details === '')         $errors[] = "Details are required.";
if (empty($category_id))     $errors[] = "Category is required.";
if ($start_price === null || $start_price < 0)
  $errors[] = "Starting price is required and must be non-negative.";
if ($end_time_in === null || $end_time_in === '')
  $errors[] = "End date is required.";

if (!empty($errors)) {
  echo '<div class="container mt-4"><div class="alert alert-danger"><ul>';
  foreach ($errors as $e) {
    echo '<li>' . htmlspecialchars($e) . '</li>';
  }
  echo '</ul></div>
        <a href="create_auction.php" class="btn btn-secondary">Back to create auction</a>
        </div>';
  include_once("footer.php");
  exit();
}

// HTML datetime-local → MySQL DATETIME
$end_time = date('Y-m-d H:i:s', strtotime($end_time_in));

// ---------- 3. 找当前用户的 seller_id ----------
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

// ---------- 4. 处理图片上传（可选） ----------
$image_path = null; // 默认没有图片

if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {

  // 检查是否上传成功
  if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Image upload failed (error code ' . (int)$_FILES['item_image']['error'] . ').
          </div></div>';
    include_once("footer.php");
    exit();
  }

  // 限制大小：2MB
  if ($_FILES['item_image']['size'] > 2 * 1024 * 1024) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Image too large (max 2MB).
          </div></div>';
    include_once("footer.php");
    exit();
  }

  // 临时文件
  $tmp_name = $_FILES['item_image']['tmp_name'];

  // 确认是图片
  $info = getimagesize($tmp_name);
  if ($info === false) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Uploaded file is not a valid image.
          </div></div>';
    include_once("footer.php");
    exit();
  }

  // 扩展名
  $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Only JPG, PNG or GIF images are allowed.
          </div></div>';
    include_once("footer.php");
    exit();
  }

  // 新文件名
  $new_name = 'item_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;

  // 1）文件系统路径（用于 move_uploaded_file）
  $upload_dir_fs = __DIR__ . '/img/items/';
  // 2）Web 路径（存数据库，给浏览器访问）
  $upload_dir_web = 'img/items/';

  if (!is_dir($upload_dir_fs)) {
    mkdir($upload_dir_fs, 0777, true);
  }

  $dest_path_fs  = $upload_dir_fs . $new_name;   // 服务器真实路径
  $dest_path_web = $upload_dir_web . $new_name;  // 存 DB 的路径

  if (!move_uploaded_file($tmp_name, $dest_path_fs)) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Failed to move uploaded image.
          </div></div>';
    include_once("footer.php");
    exit();
  }

  // 存到 DB 的路径
  $image_path = $dest_path_web;
}

// ---------- 5. 插入 items ----------
$condition = 'New';  // 先写死为 New

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

// ---------- 6. 插入 auctions ----------
$status            = 'active';
$currentHighestBid = null;

$astmt = $mysqli->prepare("
  INSERT INTO auctions (item_id, start_price, reserve_price, start_time, end_time, status, currentHighestBid)
  VALUES (?, ?, ?, NOW(), ?, ?, ?)
");
$astmt->bind_param("iddssd", $item_id, $start_price, $reserve, $end_time, $status, $currentHighestBid);

if (!$astmt->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Failed to create auction: ' . htmlspecialchars($astmt->error) . '
        </div></div>';
  $astmt->close();
  include_once("footer.php");
  exit();
}
$astmt->close();

// ---------- 7. 成功提示 ----------
echo '<div class="container mt-4">
        <div class="alert alert-success">
          Auction created successfully!
        </div>
        <a href="listing.php?item_id=' . htmlspecialchars($item_id) . '" class="btn btn-primary">View listing</a>
        <a href="mylistings.php" class="btn btn-secondary ml-2">Back to My Listings</a>
      </div>';

include_once("footer.php");
?>
