<?php
require 'header.php'; 


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}


$accountType          = $_POST['accountType']          ?? 'buyer'; 
$email                = trim($_POST['email']           ?? '');
$password             = $_POST['password']             ?? '';
$passwordConfirmation = $_POST['passwordConfirmation'] ?? '';

$errors = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
}
if ($password === '' || strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
}
if ($password !== $passwordConfirmation) {
    $errors[] = "Passwords do not match.";
}
if (!in_array($accountType, ['buyer', 'seller'])) {
    $errors[] = "Invalid account type.";
}

if (!empty($errors)) {
    include 'header.php';  

    echo '<div class="container mt-4"><div class="alert alert-danger"><ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul><a href="register.php" class="btn btn-secondary mt-2">Back to register</a></div></div>';

    include 'footer.php';
    exit();
}


$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            This email is already registered.
          </div><a href="register.php" class="btn btn-secondary mt-2">Back to register</a></div>';
    $stmt->close();
    include 'footer.php';
    exit();
}
$stmt->close();


$hash         = password_hash($password, PASSWORD_DEFAULT);

$display_name = $email;
$phone_number = '';

$stmt = $mysqli->prepare(
    "INSERT INTO users (email, password_hash, display_name, phone_number)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("ssss", $email, $hash, $display_name, $phone_number);

if (!$stmt->execute()) {
    echo '<div class="container mt-4"><div class="alert alert-danger">
            Failed to create account: ' . htmlspecialchars($stmt->error) . '
          </div></div>';
    $stmt->close();
    include 'footer.php';
    exit();
}

$user_id = $mysqli->insert_id;
$stmt->close();


if ($accountType === 'buyer') {
    $r = $mysqli->prepare("INSERT INTO buyers (user_id) VALUES (?)");
} else { // seller
    $r = $mysqli->prepare("INSERT INTO sellers (user_id) VALUES (?)");
}
$r->bind_param("i", $user_id);
$r->execute();
$r->close();


$_SESSION['logged_in']    = true;
$_SESSION['user_id']      = $user_id;
$_SESSION['account_type'] = $accountType;


echo '<div class="container mt-4">
        <div class="alert alert-success">
          Account created successfully! You are now logged in as <strong>'
          . htmlspecialchars($accountType) .
          '</strong>.
        </div>
        <a href="browse.php" class="btn btn-primary">Go to Browse</a>
      </div>';

include 'footer.php';
?>
