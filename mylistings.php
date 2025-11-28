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
    i.image_path,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.end_time,
    a.status,
    COUNT(br.bid_id) AS num_bids
  FROM auctions a
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  WHERE i.seller_id = ?
  GROUP BY i.item_id, i.title, i.description, i.image_path, price, a.end_time, a.status
  ORDER BY a.end_time DESC
";

$list_stmt = $mysqli->prepare($sql);
$list_stmt->bind_param("i", $seller_id);
$list_stmt->execute();
$list_res = $list_stmt->get_result();
?>

<div class="container mt-4">
  <h2 class="my-3">Sales record</h2>

<?php
if ($list_res->num_rows === 0) {
  echo '<div class="browse-empty mt-3">You have not created any auctions yet.</div>';
} else {
  echo '<div class="browse-list mt-3">';
  $now = new DateTime();

  while ($row = $list_res->fetch_assoc()) {
    $item_id       = $row['item_id'];
    $title         = $row['title'];
    $description   = $row['description'];
    $image_path    = $row['image_path'] ?: 'img/items/default.png';
    $current_price = (float)$row['price'];
    $num_bids      = (int)$row['num_bids'];
    $end_date      = new DateTime($row['end_time']);

    if ($end_date > $now) {
      $time_to_end = date_diff($now, $end_date);
      $meta_text = $num_bids . ' bid' . ($num_bids == 1 ? '' : 's') . ' · ' . display_time_remaining($time_to_end) . ' remaining';
    } else {
      $meta_text = $num_bids . ' bid' . ($num_bids == 1 ? '' : 's') . ' · Auction ended';
    }
    ?>
    <div class="browse-item">

      <div class="browse-thumb">
        <img src="<?php echo htmlspecialchars($image_path); ?>">
      </div>

      <div class="browse-info-row">
        <div class="browse-info-left">
          <a href="listing.php?item_id=<?php echo $item_id; ?>" class="browse-title">
            <?php echo htmlspecialchars($title); ?>
          </a>
          <div class="browse-sub"><?php echo htmlspecialchars($description); ?></div>
        </div>

        <div class="browse-info-right">
          <div class="browse-price">£<?php echo number_format($current_price, 2); ?></div>
          <div class="browse-meta"><?php echo $meta_text; ?></div>
        </div>
      </div>

    </div>
    <?php
  }

  echo '</div>';
}

$list_stmt->close();
?>

</div>

<?php include_once("footer.php") ?>
