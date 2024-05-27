<?php
require('top.inc.php');

// Fetch all pending deposits
$stmt = $con->prepare("SELECT * FROM deposits WHERE status = 'Pending'");
$stmt->execute();
$deposits = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <title>Deposits</title>
  <style>
    .thumbnail {
      width: 100px;
      cursor: pointer;
    }

    .modal-img {
      width: 100%;
    }
  </style>
</head>

<body>
  <div class="container mt-4">
    <table class="table table-bordered">
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
        <?php while ($deposit = $deposits->fetch_assoc()) : ?>
          <tr>
            <td><?php echo htmlspecialchars($deposit['id']); ?></td>
            <td><?php echo htmlspecialchars($deposit['user_id']); ?></td>
            <td><?php echo htmlspecialchars($deposit['amount']); ?></td>
            <td>
              <img src="<?php echo PRODUCT_IMAGE_SITE_PATH . $deposit['screenshot']; ?>" class="thumbnail" data-toggle="modal" data-target="#imageModal" data-src="<?php echo PRODUCT_IMAGE_SITE_PATH . $deposit['screenshot']; ?>">
            </td>
            <td><?php echo htmlspecialchars($deposit['transaction_id']); ?></td>
            <td><?php echo htmlspecialchars($deposit['status']); ?></td>
            <td>
              <form method="post" action="admin_process_deposit.php">
                <input type="hidden" name="deposit_id" value="<?php echo htmlspecialchars($deposit['id']); ?>">
                <input type="submit" name="action" value="Accept" class="btn btn-success">
                <input type="submit" name="action" value="Reject" class="btn btn-danger">
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal to display image -->
  <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-body">
          <img src="" class="modal-img" id="modalImage">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript to handle image click and modal display -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(document).ready(function() {
      $('.thumbnail').on('click', function() {
        var src = $(this).data('src');
        $('#modalImage').attr('src', src);
      });
    });
  </script>
</body>

</html>

<?php require('footer.inc.php'); ?>