<?php include_once("header.php") ?>
<?php require("utilities.php") ?>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'seller') {
  header('Location: browse.php');
  exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("SELECT seller_id FROM sellers WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">
          You are not registered as a seller.
        </div></div>';
  include 'footer.php';
  exit();
}
$row = $res->fetch_assoc();
$seller_id = $row['seller_id'];
$stmt->close();

$sql = "
  SELECT 
    i.item_id,
    i.title,
    i.description,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.end_time,
    a.status,
    COUNT(br.bid_id) AS num_bids
  FROM auctions a
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  WHERE i.seller_id = ?
  GROUP BY i.item_id, i.title, i.description, price, a.end_time, a.status
  ORDER BY a.end_time DESC
";

$list_stmt = $mysqli->prepare($sql);
$list_stmt->bind_param("i", $seller_id);
$list_stmt->execute();
$list_res = $list_stmt->get_result();
?>

<div class="container mt-4">
  <h2 class="my-3">My listings</h2>

  <ul class="list-group">
<?php
if ($list_res->num_rows === 0) {
  echo '<li class="list-group-item">You have not created any auctions yet.</li>';
} else {
  while ($row = $list_res->fetch_assoc()) {
    $item_id      = $row['item_id'];
    $title        = $row['title'];
    $description  = $row['description'];
    $current_price= $row['price'];
    $num_bids     = $row['num_bids'];
    $end_date     = new DateTime($row['end_time']);

    print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);
  }
}
$list_stmt->close();
?>
  </ul>
</div>

<?php include_once("footer.php") ?>
