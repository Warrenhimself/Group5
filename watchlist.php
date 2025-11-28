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
    i.image_path,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.end_time,
    COUNT(br.bid_id) AS num_bids
  FROM watchlist w
  JOIN auctions a ON w.auction_id = a.auction_id
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  WHERE w.user_id = ?
  GROUP BY i.item_id, i.title, i.description, i.image_path, price, a.end_time
  ORDER BY a.end_time ASC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<?php
if ($result->num_rows === 0) {
  echo '<div class="watchlist-empty mt-3">You are not watching any auctions yet.</div>';
} else {
  echo '<div class="watchlist-list mt-3">';
  $now = new DateTime();
  while ($row = $result->fetch_assoc()) {
    $item_id       = $row['item_id'];
    $title         = $row['title'];
    $image_path    = $row['image_path'] ?: 'img/items/default.png';
    $current_price = $row['price'];
    $num_bids      = $row['num_bids'];
    $end_date      = new DateTime($row['end_time']);

    if ($end_date > $now) {
      $time_to_end = date_diff($now, $end_date);
     $meta_text =
  $num_bids . ' bid' . ($num_bids == 1 ? '' : 's') .
  ' · ' . display_time_remaining($time_to_end) . ' remaining';

    } else {
      $meta_text = 'Auction ended';
    }
    ?>
    <div class="watch-row">

      <a class="watch-thumb" href="listing.php?item_id=<?php echo $item_id; ?>">
        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="">
      </a>

      <a class="watch-info" href="listing.php?item_id=<?php echo $item_id; ?>">
        <div class="watch-title"><?php echo htmlspecialchars($title); ?></div>
        <div class="watch-sub">Auction</div>
        <div class="watch-price">£<?php echo number_format($current_price, 2); ?></div>
        <div class="watch-meta"><?php echo htmlspecialchars($meta_text); ?></div>
      </a>

      <div class="watch-actions">
        <a href="listing.php?item_id=<?php echo $item_id; ?>" class="btn btn-watch-primary">
          Bid now
        </a>
      </div>

    </div>
    <?php
  }
  echo '</div>';
}
?>

</div>

<?php include_once("footer.php")?>
