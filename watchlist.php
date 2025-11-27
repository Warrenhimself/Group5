<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">Watchlist</h2>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'buyer') {
  echo '<div class="alert alert-danger mt-3">You must be logged in as a buyer to view your watchlist.</div>';
  include_once("footer.php");
  exit();
}

$user_id = $_SESSION['user_id'];

$sql = "
  SELECT 
    i.item_id,
    i.title,
    i.description,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.end_time,
    COUNT(br.bid_id) AS num_bids
  FROM watchlist w
  JOIN auctions a ON w.auction_id = a.auction_id
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  WHERE w.user_id = ?
  GROUP BY i.item_id, i.title, i.description, price, a.end_time
  ORDER BY a.end_time ASC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<ul class="list-group mt-3">
<?php
if ($result->num_rows === 0) {
  echo '<li class="list-group-item">You are not watching any auctions yet.</li>';
} else {
  while ($row = $result->fetch_assoc()) {
    $item_id      = $row['item_id'];
    $title        = $row['title'];
    $description  = $row['description'];
    $current_price= $row['price'];
    $num_bids     = $row['num_bids'];
    $end_date     = new DateTime($row['end_time']);

    print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);
  }
}
?>
</ul>

</div>

<?php include_once("footer.php")?>
