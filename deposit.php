<?php
session_start();
require('header.php');

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
  echo "<script>window.location.href = 'index.php';</script>";
  exit();
}

$user_id = $_SESSION['user_id'];
$min_amount = 10; // Minimum deposit amount

$deposit_message = ""; // Variable to hold messages for the user

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $amount = $_POST['amount'];
  $transaction_id = $_POST['transaction_id'];
  $screenshot = $_FILES['screenshot']['name'];
  $target_dir = PRODUCT_IMAGE_SERVER_PATH; // Use server path to store the file
  $target_file = $target_dir . basename($screenshot);

  // Validate amount
  if ($amount < $min_amount) {
    $deposit_message = "The deposit amount should be at least $$min_amount.";
  } else {
    // Check if transaction ID already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM deposits WHERE transaction_id = ?");
    $stmt->bind_param("s", $transaction_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
      $deposit_message = "This transaction ID has already been used.";
    } else {
      // Move uploaded file to the target directory
      if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $target_file)) {
        // Insert deposit details into the database with status 'Pending'
        $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, screenshot, transaction_id, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iiss", $user_id, $amount, $screenshot, $transaction_id);
        $stmt->execute();
        $stmt->close();

        // JavaScript redirect to dashboard page
        echo "<script>alert('Deposit submitted successfully.'); window.location.href = 'dashboard.php';</script>";
        exit();
      } else {
        $deposit_message = "Sorry, there was an error uploading your file.";
      }
    }
  }
}
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4>Make a Deposit</h4>
        </div>
        <div class="card-body">
          <?php if ($deposit_message) : ?>
            <div class="alert alert-danger" style="color: white;">
              <?php echo $deposit_message; ?>
            </div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data">
            <div class="form-group">
              <label for="amount">Amount</label>
              <input type="number" class="form-control" id="amount" name="amount" required>
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