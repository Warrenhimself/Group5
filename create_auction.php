<?php include_once("header.php")?>

<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['account_type'] !== 'seller') {
  header('Location: browse.php');
  exit();
}

$prefill_category_id = null;

if (isset($_GET['copy_item']) && ctype_digit($_GET['copy_item'])) {
    $copy_item_id = (int)$_GET['copy_item'];
    $stmt = $mysqli->prepare("SELECT category_id FROM items WHERE item_id = ? LIMIT 1");
    $stmt->bind_param("i", $copy_item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $prefill_category_id = (int)$row['category_id'];
    }
    $stmt->close();
}

$now_local = date('Y-m-d\TH:i');
?>

<div class="container">

<div style="max-width: 800px; margin: 10px auto">
  <h2 class="my-3">Create new auction</h2>
  <div class="card">
    <div class="card-body">
      
      <form method="post" action="create_auction_result.php" enctype="multipart/form-data">

        <div class="form-group row">
          <label for="auctionTitle" class="col-sm-2 col-form-label text-right">Title of auction</label>
          <div class="col-sm-10">
            <input type="text" class="form-control" id="auctionTitle" name="title" required>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span> A short description of the item.</small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionDetails" class="col-sm-2 col-form-label text-right">Details</label>
          <div class="col-sm-10">
            <textarea class="form-control" id="auctionDetails" name="description" rows="4" required></textarea>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionCategory" class="col-sm-2 col-form-label text-right">Category</label>
          <div class="col-sm-10">
            <select class="form-control" id="auctionCategory" name="category" required>
              <option value="">Choose...</option>
              <?php
              $catResult = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
              while ($cat = $catResult->fetch_assoc()) {
                $selected = ($prefill_category_id !== null && $prefill_category_id == $cat['category_id']) ? 'selected' : '';
                echo '<option value="' . $cat['category_id'] . '" ' . $selected . '>' . htmlspecialchars($cat['category_name']) . '</option>';
              }
              ?>
            </select>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionStartPrice" class="col-sm-2 col-form-label text-right">Starting price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionStartPrice" name="start_price" step="0.01" min="0" required>
            </div>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionReservePrice" class="col-sm-2 col-form-label text-right">Reserve price</label>
          <div class="col-sm-10">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">£</span>
              </div>
              <input type="number" class="form-control" id="auctionReservePrice" name="reserve_price" step="0.01" min="0">
            </div>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionImage" class="col-sm-2 col-form-label text-right">Item image</label>
          <div class="col-sm-10">
            <input type="file" class="form-control-file" id="auctionImage" name="item_image" accept="image/*">
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionStartDate" class="col-sm-2 col-form-label text-right">Start date</label>
          <div class="col-sm-10">
            <input type="datetime-local"
                   class="form-control"
                   id="auctionStartDate"
                   name="start_time"
                   value="<?php echo $now_local; ?>"
                   required>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <div class="form-group row">
          <label for="auctionEndDate" class="col-sm-2 col-form-label text-right">End date</label>
          <div class="col-sm-10">
            <input type="datetime-local" class="form-control" id="auctionEndDate" name="end_time" required>
            <small class="form-text text-muted"><span class="text-danger">* Required.</span></small>
          </div>
        </div>

        <button type="submit" class="btn btn-primary form-control">Create Auction</button>

      </form>
    </div>
  </div>
</div>

</div>

<?php include_once("footer.php")?>
