<?php
require 'header.php';
require 'utilities.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'buyer') {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You must be logged in as a buyer to place a bid.
        </div></div>';
  include 'footer.php';
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['auction_id']) || !isset($_POST['bid_amount'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Invalid bid request.
        </div></div>';
  include 'footer.php';
  exit();
}

$auction_id = (int)$_POST['auction_id'];
$bid_amount = (float)$_POST['bid_amount'];

if ($bid_amount <= 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Bid must be positive.
        </div></div>';
  include 'footer.php';
  exit();
}


$sql = "
  SELECT 
    a.auction_id,
    a.item_id,
    a.start_price,
    a.currentHighestBid,
    a.end_time,
    a.status,
    i.seller_id,
    s.user_id AS seller_user_id
  FROM auctions a
  JOIN items   i ON a.item_id = i.item_id
  JOIN sellers s ON i.seller_id = s.seller_id
  WHERE a.auction_id = ?
  LIMIT 1
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Auction not found.
        </div></div>';
  $stmt->close();
  include 'footer.php';
  exit();
}

$auction = $res->fetch_assoc();
$stmt->close();

$now      = new DateTime();
$end_time = new DateTime($auction['end_time']);


$current_user_id   = (int)$_SESSION['user_id'];
$seller_user_id    = (int)$auction['seller_user_id'];

if ($current_user_id === $seller_user_id) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You cannot bid on your own auction.
        </div></div>';
  include 'footer.php';
  exit();
}


if ($auction['status'] !== 'active' || $now >= $end_time) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          This auction has ended.
        </div></div>';
  include 'footer.php';
  exit();
}


$current_price = $auction['currentHighestBid'] !== null
  ? (float)$auction['currentHighestBid']
  : (float)$auction['start_price'];


if ($bid_amount <= $current_price) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Your bid must be higher than the current bid (£' . number_format($current_price, 2) . ').
        </div></div>';
  include 'footer.php';
  exit();
}


$user_id = $current_user_id;

$bstmt = $mysqli->prepare("SELECT buyer_id FROM buyers WHERE user_id = ? LIMIT 1");
$bstmt->bind_param("i", $user_id);
$bstmt->execute();
$bres = $bstmt->get_result();
if ($bres->num_rows === 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You are not registered as a buyer.
        </div></div>';
  $bstmt->close();
  include 'footer.php';
  exit();
}
$buyer = $bres->fetch_assoc();
$buyer_id = (int)$buyer['buyer_id'];
$bstmt->close();


$ins = $mysqli->prepare("
  INSERT INTO bid_record (auction_id, buyer_id, bid_amount, bid_time)
  VALUES (?, ?, ?, NOW())
");
$ins->bind_param("iid", $auction_id, $buyer_id, $bid_amount);

if (!$ins->execute()) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          Failed to place bid: ' . htmlspecialchars($ins->error) . '
        </div></div>';
  $ins->close();
  include 'footer.php';
  exit();
}
$ins->close();

$up = $mysqli->prepare("UPDATE auctions SET currentHighestBid = ? WHERE auction_id = ?");
$up->bind_param("di", $bid_amount, $auction_id);
$up->execute();
$up->close();

$item_id = $auction['item_id'];

echo '<div class="container mt-4">
        <div class="alert alert-success">
          Bid placed successfully!
        </div>
        <a href="listing.php?item_id=' . htmlspecialchars($item_id) . '" class="btn btn-primary">Back to listing</a>
        <a href="browse.php" class="btn btn-secondary ml-2">Back to browse</a>
      </div>';

include 'footer.php';
?>
