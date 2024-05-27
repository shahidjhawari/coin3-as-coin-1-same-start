<?php
session_start();
require('header.php');

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Check if there is any pending transaction for the user
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($pending_transaction) {
  // If a pending transaction exists, display a message and exit
  echo '<div class="container mt-5"><div class="alert alert-warning text-center" role="alert">You already have a pending transaction. Please wait for it to be processed.</div></div>';
  require('footer.php');
  exit();
}

// Handle form submission if POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $amount = 10; // Fixed amount
  $transaction_id = $_POST['transaction_id'];
  $screenshot = $_FILES['screenshot']['name'];
  $target_dir = PRODUCT_IMAGE_SERVER_PATH;
  $target_file = $target_dir . basename($screenshot);

  // Move uploaded file to the target directory
  if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $target_file)) {
    // Insert transaction details into the database
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, screenshot, transaction_id, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("idss", $user_id, $amount, $screenshot, $transaction_id);
    $stmt->execute();
    $stmt->close();
    echo '<div class="container mt-5"><div class="alert alert-success text-center" role="alert">Transaction submitted successfully. Please wait for it to be processed.</div></div>';
  } else {
    echo '<div class="container mt-5"><div class="alert alert-danger text-center" role="alert">Sorry, there was an error uploading your file.</div></div>';
  }
}
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4>Submit Transaction</h4>
        </div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <div class="form-group">
              <label for="amount">Amount</label>
              <input type="text" class="form-control" id="amount" name="amount" value="$10" readonly>
            </div>
            <div class="form-group">
              <label for="transaction_id">Transaction ID</label>
              <input type="text" class="form-control" id="transaction_id" name="transaction_id" required>
            </div>
            <div class="form-group">
              <label for="screenshot">Screenshot</label>
              <input type="file" class="form-control-file" id="screenshot" name="screenshot" required>
            </div>
            <button type="submit" class="btn btn-info btn-block">Submit</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require('footer.php'); ?>
