<?php
require('top.inc.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $deposit_id = $_POST['deposit_id'];
  $action = $_POST['action'];

  // Update the status of the deposit based on the action
  if ($action == 'Accept') {
    $stmt = $con->prepare("UPDATE deposits SET status = 'Accepted' WHERE id = ?");
  } elseif ($action == 'Reject') {
    $stmt = $con->prepare("UPDATE deposits SET status = 'Rejected' WHERE id = ?");
  }

  $stmt->bind_param("i", $deposit_id);
  $stmt->execute();
  $stmt->close();

  header("Location: admin_deposits.php");
  exit();
}
