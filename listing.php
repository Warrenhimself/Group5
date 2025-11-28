<?php include_once("header.php")?>
<?php require("utilities.php")?>

<?php
if (!isset($_GET['item_id']) || !ctype_digit($_GET['item_id'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Invalid item.</div></div>';
  include_once("footer.php");
  exit();
}

$item_id = (int)$_GET['item_id'];

/** @var mysqli $mysqli */
$sql = "
  SELECT 
    i.item_id,
    i.title,
    i.description,
    i.image_path,
    a.auction_id,
    COALESCE(a.currentHighestBid, a.start_price) AS current_price,
    a.start_price,
    a.start_time,
    a.end_time,
    a.status,
    COUNT(br.bid_id) AS num_bids,
    a.winnerId,
    u.display_name AS winner_name
  FROM items i
  JOIN auctions a ON i.item_id = a.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  LEFT JOIN buyers b ON a.winnerId = b.buyer_id
  LEFT JOIN users  u ON b.user_id  = u.user_id
  WHERE i.item_id = ?
  GROUP BY 
    i.item_id, i.title, i.description, i.image_path,
    a.auction_id, current_price, a.start_price,
    a.start_time, a.end_time, a.status, a.winnerId, u.display_name
  LIMIT 1
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Listing not found.</div></div>';
  $stmt->close();
  include_once("footer.php");
  exit();
}

$row = $res->fetch_assoc();
$stmt->close();

$title         = $row['title'];
$description   = $row['description'];
$image_path    = $row['image_path'];
$auction_id    = $row['auction_id'];
$current_price = (float)$row['current_price'];
$num_bids      = (int)$row['num_bids'];
$start_time    = new DateTime($row['start_time']);
$end_time      = new DateTime($row['end_time']);
$status        = $row['status'];
$winner_id     = $row['winnerId'];
$winner_name   = $row['winner_name'];

$now = new DateTime();

$has_started = ($now >= $start_time);
$has_ended   = ($now > $end_time || $status !== 'active');

$time_remaining = '';
if ($has_started && !$has_ended) {
  $time_to_end = date_diff($now, $end_time);
  $time_remaining = ' (in ' . display_time_remaining($time_to_end) . ')';
}

$has_session = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$watching = false;

if ($has_session) {
  $user_id = $_SESSION['user_id'];
  $wstmt = $mysqli->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND auction_id = ? LIMIT 1");
  $wstmt->bind_param("ii", $user_id, $auction_id);
  $wstmt->execute();
  $wres = $wstmt->get_result();
  if ($wres->num_rows > 0) {
    $watching = true;
  }
  $wstmt->close();
}
?>

<div class="container">

  <div class="row">
    <div class="col-sm-8">
      <h2 class="my-3"><?php echo htmlspecialchars($title); ?></h2>
      <p class="text-muted">
        Starts: <?php echo $start_time->format('j M Y H:i'); ?> |
        Ends: <?php echo $end_time->format('j M Y H:i'); ?>
      </p>
    </div>

    <div class="col-sm-4 align-self-center">
<?php if ($has_started && !$has_ended && $status === 'active'): ?>
      <div id="watch_nowatch" <?php if ($has_session && $watching) echo 'style="display:none"';?>>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addToWatchlist()">+ Add to watchlist</button>
      </div>
      <div id="watch_watching" <?php if (!$has_session || !$watching) echo 'style="display:none"';?>>
        <button type="button" class="btn btn-success btn-sm" disabled>Watching</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeFromWatchlist()">Remove watch</button>
      </div>
<?php endif; ?>
    </div>
  </div>

  <div class="row mt-3">

    <div class="col-sm-8">
      <?php if (!empty($image_path)): ?>
        <img src="<?php echo htmlspecialchars($image_path); ?>" 
             alt="Item image"
             class="auction-item-image">
      <?php endif; ?>

      <div class="itemDescription">
        <?php echo nl2br(htmlspecialchars($description)); ?>
      </div>
    </div>

    <div class="col-sm-4">

<?php if (!$has_started): ?>

      <p class="font-weight-bold">
        Auction has not started yet.
      </p>
      <p>
        Starts on <?php echo $start_time->format('j M Y H:i'); ?>.
      </p>
      <p class="text-muted">
        Bidding and watchlist will be available after the start time.
      </p>

<?php elseif ($has_ended): ?>

      <p>
        Auction ended on <?php echo $end_time->format('j M Y H:i'); ?> – 
        <?php echo $num_bids; ?> bid<?php echo $num_bids == 1 ? '' : 's'; ?>.
      </p>
      <p class="lead">Final price: £<?php echo number_format($current_price, 2); ?></p>

<?php if ($winner_id): ?>
      <p>Winner: <?php echo htmlspecialchars($winner_name ?: ('Buyer #' . $winner_id)); ?></p>
<?php else: ?>
      <p>No winner (no valid winning bid or reserve not met).</p>
<?php endif; ?>

<?php else:   ?>

      <p>
        Auction ends <?php echo $end_time->format('j M Y H:i') . $time_remaining; ?>
      </p>
      <p class="lead">Current bid: £<?php echo number_format($current_price, 2); ?></p>
      <p><?php echo $num_bids; ?> bid<?php echo $num_bids == 1 ? '' : 's'; ?></p>

<?php if ($has_session && isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'buyer'): ?>
      <hr>
      <h5>Place a bid</h5>
      <form method="POST" action="place_bid.php">
        <input type="hidden" name="auction_id" value="<?php echo $auction_id; ?>">
        <div class="input-group mb-2">
          <div class="input-group-prepend">
            <span class="input-group-text">£</span>
          </div>
          <input type="number" class="form-control" name="bid_amount" step="0.01" min="0" required>
        </div>
        <button type="submit" class="btn btn-primary form-control">Place bid</button>
      </form>
<?php else: ?>
      <p class="text-muted">You must be logged in as a buyer to place a bid.</p>
<?php endif; ?>

<?php endif; ?>

    </div>
  </div>
</div>

<?php include_once("footer.php")?>

<script>
function addToWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'add_to_watchlist', arguments: [<?php echo $auction_id;?>]},
    success: function (obj, textstatus) {
      var objT = obj.trim();
      if (objT == "success") {
        $("#watch_nowatch").hide();
        $("#watch_watching").show();
      } else {
        var mydiv = document.getElementById("watch_nowatch");
        mydiv.appendChild(document.createElement("br"));
        mydiv.appendChild(document.createTextNode("Add to watch failed. Try again later."));
      }
    }
  });
}

function removeFromWatchlist() {
  $.ajax('watchlist_funcs.php', {
    type: "POST",
    data: {functionname: 'remove_from_watchlist', arguments: [<?php echo $auction_id;?>]},
    success: function (obj, textstatus) {
      var objT = obj.trim();
      if (objT == "success") {
        $("#watch_watching").hide();
        $("#watch_nowatch").show();
      } else {
        var mydiv = document.getElementById("watch_watching");
        mydiv.appendChild(document.createElement("br"));
        mydiv.appendChild(document.createTextNode("Watch removal failed. Try again later."));
      }
    }
  });
}
</script>
