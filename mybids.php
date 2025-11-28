<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My bids</h2>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'buyer') {
  echo '<div class="alert alert-danger mt-3">You must be logged in as a buyer to view your bids.</div>';
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
$buyer = $bres->fetch_assoc();
$buyer_id = $buyer['buyer_id'];
$bstmt->close();

$sql = "
  SELECT 
    i.item_id,
    i.title,
    i.description,
    i.image_path,
    COALESCE(a.currentHighestBid, a.start_price) AS current_price,
    MAX(br.bid_amount) AS my_bid,
    a.end_time,
    COUNT(br.bid_id) AS num_bids
  FROM bid_record br
  JOIN auctions a ON br.auction_id = a.auction_id
  JOIN items i ON a.item_id = i.item_id
  WHERE br.buyer_id = ?
  GROUP BY 
    i.item_id,
    i.title,
    i.description,
    i.image_path,
    current_price,
    a.end_time
  ORDER BY a.end_time DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<?php
if ($result->num_rows === 0) {
  echo '<div class="mybids-empty mt-3">You have not placed any bids yet.</div>';
} else {
  echo '<div class="mybids-list mt-3">';
  $now = new DateTime();
  while ($row = $result->fetch_assoc()) {
    $item_id       = $row['item_id'];
    $title         = $row['title'];
    $description   = $row['description'];
    $image_path    = $row['image_path'] ?: "img/items/default.png";
    $current_price = (float)$row['current_price'];
    $my_bid        = (float)$row['my_bid'];
    $num_bids      = (int)$row['num_bids'];
    $end_date      = new DateTime($row['end_time']);

    if ($end_date > $now) {
      $time_to_end = date_diff($now, $end_date);
      $time_remain_str = display_time_remaining($time_to_end);
      $time_text = $time_remain_str . " remaining";
    } else {
      $time_text = "Auction ended";
    }
    ?>
    <a class="mybids-row" href="listing.php?item_id=<?php echo $item_id; ?>">

      <div class="mybids-thumb">
        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="">
      </div>

      <div class="mybids-info">
        <div class="mybids-title"><?php echo htmlspecialchars($title); ?></div>
        <div class="mybids-desc"><?php echo htmlspecialchars($description); ?></div>
      </div>

      <div class="mybids-right">
        <div class="mybids-price">£<?php echo number_format($current_price, 2); ?></div>
        <div class="mybids-yourbid">Your bid: £<?php echo number_format($my_bid, 2); ?></div>
        <div class="mybids-meta">
          <?php echo $num_bids; ?> bid<?php echo $num_bids == 1 ? '' : 's'; ?><br>
          <?php echo htmlspecialchars($time_text); ?>
        </div>
      </div>

    </a>
    <?php
  }
  echo '</div>';
}
?>

</div>

<?php include_once("footer.php")?>
