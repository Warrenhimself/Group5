<?php include_once("header.php") ?>
<?php require("utilities.php") ?>

<div class="container">

<h2 class="my-3">Auction system</h2>

<div id="searchSpecs">

<form method="get" action="browse.php">
  <div class="row">
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <input
            type="text"
            class="form-control border-left-0"
            id="keyword"
            name="keyword"
            placeholder="Search for anything"
            value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>"
          >
        </div>
      </div>
    </div>

    <div class="col-md-3 pr-0">
      <div class="form-group">
        <label for="cat" class="sr-only">Search within:</label>
        <select class="form-control" id="cat" name="cat">
          <option value="all">All categories</option>
          <?php
         
          $catResult = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
          $selectedCat = $_GET['cat'] ?? 'all';
          while ($cat = $catResult->fetch_assoc()) {
              $sel = ($selectedCat == $cat['category_id']) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($cat['category_id']) . '" ' . $sel . '>'
                   . htmlspecialchars($cat['category_name']) . '</option>';
          }
          ?>
        </select>
      </div>
    </div>

    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <select class="form-control" id="order_by" name="order_by">
          <?php
          $order_by = $_GET['order_by'] ?? 'pricelow';
          ?>
          <option value="pricelow"  <?php if ($order_by == 'pricelow')  echo 'selected'; ?>>Price (low to high)</option>
          <option value="pricehigh" <?php if ($order_by == 'pricehigh') echo 'selected'; ?>>Price (high to low)</option>
          <option value="date"      <?php if ($order_by == 'date')      echo 'selected'; ?>>Soonest expiry</option>
        </select>
      </div>
    </div>

    <div class="col-md-1 px-0">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </div>
</form>
</div> 

</div>

<?php

$keyword  = $_GET['keyword']  ?? '';
$category = $_GET['cat']      ?? 'all';
$ordering = $_GET['order_by'] ?? 'pricelow';

if (!isset($_GET['page']) || !ctype_digit($_GET['page'])) {
  $curr_page = 1;
} else {
  $curr_page = max(1, (int)$_GET['page']);
}

$results_per_page = 10;
$offset = ($curr_page - 1) * $results_per_page;


$conditions = [
  "a.status = 'active'",
  "a.start_time <= NOW()"  
];
$params = [];
$types  = "";


if ($keyword !== '') {
  $conditions[] = "(i.title LIKE ? OR i.description LIKE ?)";
  $like = '%' . $keyword . '%';
  $params[] = $like;
  $params[] = $like;
  $types   .= "ss";
}


if ($category !== 'all') {
  $conditions[] = "i.category_id = ?";
  $params[] = (int)$category;
  $types   .= "i";
}

$where_sql = implode(" AND ", $conditions);


$count_sql = "
  SELECT COUNT(DISTINCT a.auction_id) AS total
  FROM auctions a
  JOIN items i ON a.item_id = i.item_id
  WHERE $where_sql
";

$count_stmt = $mysqli->prepare($count_sql);
if ($types !== "") {
  $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$row = $count_result->fetch_assoc();
$num_results = (int)$row['total'];
$count_stmt->close();

$max_page = max(1, ceil($num_results / $results_per_page));


switch ($ordering) {
  case 'pricehigh':
    $order_sql = "COALESCE(a.currentHighestBid, a.start_price) DESC";
    break;
  case 'date':
    $order_sql = "a.end_time ASC";
    break;
  case 'pricelow':
  default:
    $order_sql = "COALESCE(a.currentHighestBid, a.start_price) ASC";
    break;
}

$list_sql = "
  SELECT 
    i.item_id,
    i.title,
    i.description,
    i.image_path,
    COALESCE(a.currentHighestBid, a.start_price) AS price,
    a.start_time,
    a.end_time,
    COUNT(br.bid_id) AS num_bids
  FROM auctions a
  JOIN items i ON a.item_id = i.item_id
  LEFT JOIN bid_record br ON a.auction_id = br.auction_id
  WHERE $where_sql
  GROUP BY i.item_id, i.title, i.description, i.image_path, price, a.end_time
  ORDER BY $order_sql
  LIMIT ? OFFSET ?
";

$list_stmt = $mysqli->prepare($list_sql);


$params_with_page = $params;
$types_with_page  = $types . "ii";
$params_with_page[] = $results_per_page;
$params_with_page[] = $offset;

$list_stmt->bind_param($types_with_page, ...$params_with_page);
$list_stmt->execute();
$list_result = $list_stmt->get_result();
?>

<div class="container mt-5">

<?php
if ($num_results == 0) {
  echo '<div class="browse-empty">No listings found. Try changing your search or filters.</div>';
} else {
  echo '<div class="browse-list">';
  $now = new DateTime();
  while ($row = $list_result->fetch_assoc()) {
    $item_id       = $row['item_id'];
    $title         = $row['title'];
    $description   = $row['description'];
    $image_path    = $row['image_path'] ?: 'img/items/default.png';
    $current_price = $row['price'];
    $num_bids      = $row['num_bids'];
    $end_date      = new DateTime($row['end_time']);

    if ($end_date > $now) {
      $time_to_end = date_diff($now, $end_date);
      $time_remain_str = display_time_remaining($time_to_end);
      $meta_text = $num_bids . ' bid' . ($num_bids == 1 ? '' : 's') . ' · ' .
                   'ends ' . $end_date->format('j M Y H:i') . ' (' . $time_remain_str . ')';
    } else {
      $meta_text = 'Auction ended';
    }
    ?>
    <a class="browse-item" href="listing.php?item_id=<?php echo $item_id; ?>">
      <div class="browse-thumb">
        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($title); ?>">
      </div>
      <div class="browse-info-row">

  <div class="browse-info-left">
    <div class="browse-title"><?php echo htmlspecialchars($title); ?></div>
    <div class="browse-sub">Auction</div>
    <div class="browse-meta"><?php echo htmlspecialchars($meta_text); ?></div>
  </div>

  <div class="browse-info-right">
    <div class="browse-price">
      £<?php echo number_format($current_price, 2); ?>
    </div>
  </div>

</div>

    </a>
    <?php
  }
  echo '</div>';
}
$list_stmt->close();
?>

<nav aria-label="Search results pages" class="mt-5">

  <ul class="pagination justify-content-center">
  
<?php

  $querystring = "";
  foreach ($_GET as $key => $value) {
    if ($key != "page") {
      $querystring .= "$key=" . urlencode($value) . "&amp;";
    }
  }
  
  $high_page_boost = max(3 - $curr_page, 0);
  $low_page_boost = max(2 - ($max_page - $curr_page), 0);
  $low_page = max(1, $curr_page - 2 - $low_page_boost);
  $high_page = min($max_page, $curr_page + 2 + $high_page_boost);
  
  if ($curr_page != 1) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
        <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
        <span class="sr-only">Previous</span>
      </a>
    </li>');
  }
    
  for ($i = $low_page; $i <= $high_page; $i++) {
    if ($i == $curr_page) {
      echo('<li class="page-item active">');
    } else {
      echo('<li class="page-item">');
    }
    echo('
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a>
    </li>');
  }
  
  if ($curr_page != $max_page) {
    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '" aria-label="Next">
        <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        <span class="sr-only">Next</span>
      </a>
    </li>');
  }
?>

  </ul>
</nav>

</div>

<?php include_once("footer.php") ?>
