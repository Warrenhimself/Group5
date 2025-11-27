<?php
require 'header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

$errors = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email.";
}
if ($password === '') {
    $errors[] = "Please enter your password.";
}

if (!empty($errors)) {
    echo '<div class="container mt-4"><div class="alert alert-danger"><ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul><a href="index.php" class="btn btn-secondary mt-2">Back</a></div></div>';
    include 'footer.php';
    exit();
}

$stmt = $mysqli->prepare("SELECT user_id, password_hash, display_name FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Email or password is incorrect.
          </div><a href="index.php" class="btn btn-secondary mt-2">Back</a></div>';
    $stmt->close();
    include 'footer.php';
    exit();
}

$user = $res->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password_hash'])) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Email or password is incorrect.
          </div><a href="index.php" class="btn btn-secondary mt-2">Back</a></div>';
    include 'footer.php';
    exit();
}

$user_id = (int)$user['user_id'];

$account_type = 'buyer';

$checkSeller = $mysqli->prepare("SELECT seller_id FROM sellers WHERE user_id = ? LIMIT 1");
$checkSeller->bind_param("i", $user_id);
$checkSeller->execute();
$sellerRes = $checkSeller->get_result();
if ($sellerRes->num_rows > 0) {
    $account_type = 'seller';
}
$checkSeller->close();

$_SESSION['logged_in']    = true;
$_SESSION['user_id']      = $user_id;
$_SESSION['display_name'] = $user['display_name'];
$_SESSION['account_type'] = $account_type;

echo '<div class="container mt-4">
        <div class="alert alert-success">
          Login successful! You are logged in as <strong>' . htmlspecialchars($account_type) . '</strong>.
        </div>
        <a href="index.php" class="btn btn-primary">Go to home</a>
      </div>';

echo '<meta http-equiv="refresh" content="2;url=index.php">';

include 'footer.php';
?>
