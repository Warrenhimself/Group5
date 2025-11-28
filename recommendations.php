<?php include_once("header.php")?>

<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">Recommendations for you</h2>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'buyer') {
  echo '<div class="alert alert-danger mt-3">You must be logged in as a buyer to see recommendations.</div>';
  include_once("footer.php");
  exit();
}

$user_id = $_SESSION['user_id'];

$bstmt = $mysqli->prepare("SELECT buyer_id FROM buyers WHERE user_id = ? LIMIT 1");
$bstmt->bind_param("i", $user_id);
$bstmt->execute();
$bres = $bstmt->get_result();
if ($bres->num_rows === 0) {
  echo '<div class="alert alert-warning mt-3">You are not registered as a buyer.</div>';
  $bstmt->close();
  include_once("footer.php");
  exit();
}
$buyer     = $bres->fetch_assoc();
$buyer_id  = (int)$buyer['buyer_id'];
$bstmt->close();

$sql = "
  SELECT
    i.item_id,
    i.title,
    i.description,
    i.image_path,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.end_time,
    COUNT(DISTINCT br_all.bid_id) AS num_bids
  FROM bid_record br_me
  JOIN bid_record br_same
    ON br_me.auction_id = br_same.auction_id
    AND br_same.buyer_id <> br_me.buyer_id
  JOIN bid_record br_other
    ON br_other.buyer_id = br_same.buyer_id
  JOIN auctions a ON br_other.auction_id = a.auction_id
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br_all ON a.auction_id = br_all.auction_id
  WHERE br_me.buyer_id = ?
    AND a.status = 'active'
    AND a.auction_id NOT IN (
      SELECT DISTINCT auction_id
      FROM bid_record
      WHERE buyer_id = ?
    )
  GROUP BY i.item_id, i.title, i.description, i.image_path, price, a.end_time
  ORDER BY a.end_time ASC
  LIMIT 20
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $buyer_id, $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<?php
if ($result->num_rows === 0) {
  echo '<div class="browse-empty mt-3">No recommendations yet. Try bidding on some items first!</div>';
} else {
  echo '<div class="browse-list mt-3">';
  $now = new DateTime();
  while ($row = $result->fetch_assoc()) {
    $item_id       = $row['item_id'];
    $title         = $row['title'];
    $description   = $row['description'];
    $image_path    = $row['image_path'] ?: 'img/items/default.png';
    $current_price = $row['price'];
    $num_bids      = $row['num_bids'];
    $end_date      = new DateTime($row['end_time']);

    if ($end_date > $now) {
      $time_to_end     = date_diff($now, $end_date);
      $time_remain_str = display_time_remaining($time_to_end);
      $meta_text       = $num_bids . ' bid' . ($num_bids == 1 ? '' : 's')
                       . ' · ' . $time_remain_str . ' remaining';
    } else {
      $meta_text       = $num_bids . ' bid' . ($num_bids == 1 ? '' : 's')
                       . ' · Auction ended';
    }
    ?>
    <div class="browse-item">

      <div class="browse-thumb">
        <img src="<?php echo htmlspecialchars($image_path); ?>">
      </div>

      <div class="browse-info-row">
        <div class="browse-info-left">
          <div class="browse-title"><?php echo htmlspecialchars($title); ?></div>
          <div class="browse-sub"><?php echo htmlspecialchars($description); ?></div>
          <div class="browse-meta"><?php echo htmlspecialchars($meta_text); ?></div>

          <a href="listing.php?item_id=<?php echo $item_id; ?>" class="btn-view">
            View details
          </a>
        </div>

        <div class="browse-info-right">
          <div class="browse-price">
            £<?php echo number_format($current_price, 2); ?>
          </div>
        </div>
      </div>

    </div>
    <?php
  }
  echo '</div>';
}
?>

</div>

<?php include_once("footer.php")?>
