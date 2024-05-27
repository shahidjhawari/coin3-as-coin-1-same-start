<?php
require('top.inc.php');

// Fetch all rejected transactions
$stmt = $con->prepare("SELECT * FROM transactions WHERE status = 'accepted'");
$stmt->execute();
$rejected_transactions = $stmt->get_result();
$stmt->close();
?>


<div class="container">
    <h2>Accepted Account Activation</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Image</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($transaction = $rejected_transactions->fetch_assoc()) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['amount']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['status']); ?></td>
                    <td>
                        <img src="<?php echo PRODUCT_IMAGE_SITE_PATH . $transaction['screenshot']; ?>" class="img-fluid" alt="Screenshot" width="100">
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require('footer.inc.php'); ?>