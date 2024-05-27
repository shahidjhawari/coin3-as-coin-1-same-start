<?php
require('top.inc.php');

// Fetch all pending transactions
$stmt = $con->prepare("SELECT * FROM transactions WHERE status = 'pending'");
$stmt->execute();
$transactions = $stmt->get_result();
$stmt->close();
?>

<head>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <title>Transactions</title>
</head>

<table class="table">
  <thead>
    <tr>
      <th>ID</th>
      <th>User ID</th>
      <th>Amount</th>
      <th>Screenshot</th>
      <th>Transaction ID</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($transaction = $transactions->fetch_assoc()) : ?>
      <tr>
        <td><?php echo htmlspecialchars($transaction['id']); ?></td>
        <td><?php echo htmlspecialchars($transaction['user_id']); ?></td>
        <td><?php echo htmlspecialchars($transaction['amount']); ?></td>
        <td>
          <img src="<?php echo PRODUCT_IMAGE_SITE_PATH . $transaction['screenshot']; ?>" width="100" height="100" style="object-fit: cover; cursor: pointer;" data-toggle="modal" data-target="#imageModal<?php echo $transaction['id']; ?>">
        </td>
        <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
        <td><?php echo htmlspecialchars($transaction['status']); ?></td>
        <td>
          <form method="post" action="admin_process.php">
            <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($transaction['id']); ?>">
            <input type="submit" name="action" value="Accept" class="btn btn-success">
            <input type="submit" name="action" value="Reject" class="btn btn-danger">
          </form>
        </td>
      </tr>

      <!-- Modal -->
      <div class="modal fade" id="imageModal<?php echo $transaction['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel<?php echo $transaction['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="imageModalLabel<?php echo $transaction['id']; ?>">Screenshot</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <img src="<?php echo PRODUCT_IMAGE_SITE_PATH . $transaction['screenshot']; ?>" class="img-fluid" alt="Screenshot">
            </div>
          </div>
        </div>
      </div>

    <?php endwhile; ?>
  </tbody>
</table>

<?php require('footer.inc.php'); ?>
