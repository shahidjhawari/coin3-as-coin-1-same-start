<?php
require('connection.inc.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  //   header("Location: admin_login.php");
  //   exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $transaction_id = $_POST['transaction_id'];
  $action = $_POST['action'];

  if ($action == 'Accept') {
    $status = 'accepted';
  } else if ($action == 'Reject') {
    $status = 'rejected';
  }

  // Update transaction status
  $stmt = $con->prepare("UPDATE transactions SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $transaction_id);
  $stmt->execute();
  $stmt->close();

  header("Location: activate.php");
  exit();
}
