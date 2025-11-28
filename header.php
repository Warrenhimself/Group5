<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = false;
}
if (!isset($_SESSION['account_type'])) {
    $_SESSION['account_type'] = 'buyer';
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'auctiondb';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="css/custom.css?v=2">

  <title>Group5 Auction System</title>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark main-nav">
  <div class="container">
    <a class="navbar-brand" href="browse.php">Group5 Auction System</a>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item mx-1">
          <a class="nav-link" href="browse.php">Homepage</a>
        </li>

        <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] == 'buyer') { ?>
          <li class="nav-item mx-1">
            <a class="nav-link" href="mybids.php">My Bids</a>
          </li>
          <li class="nav-item mx-1">
            <a class="nav-link" href="watchlist.php">Watchlist</a>
          </li>
          <li class="nav-item mx-1">
            <a class="nav-link" href="recommendations.php">Recommended</a>
          </li>
        <?php } ?>

        <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] == 'seller') { ?>
          <li class="nav-item mx-1">
            <a class="nav-link" href="mylistings.php">My Item sales record</a>
          </li>
          <li class="nav-item mx-1">
            <a class="nav-link nav-cta" href="create_auction.php">+ Create auction</a>
          </li>
        <?php } ?>
      </ul>

      <ul class="navbar-nav">
        <li class="nav-item">
          <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) { ?>
            <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="logout.php">Logout</a>
          <?php } else { ?>
            <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-toggle="modal" data-target="#loginModal">
              Login
            </button>
          <?php } ?>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="modal fade" id="loginModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title">Login</h4>
      </div>

      <div class="modal-body">
        <form method="POST" action="login_result.php">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="text" class="form-control" id="email" name="email" placeholder="Email">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Password">
          </div>
          <button type="submit" class="btn btn-primary form-control">Sign in</button>
        </form>
        <div class="text-center">or <a href="register.php">create an account</a></div>
      </div>

    </div>
  </div>
</div>
