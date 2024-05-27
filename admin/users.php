<?php
require('top.inc.php');

// Fetch all users
$stmt = $con->prepare("SELECT * FROM users");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<div class="container mt-5">
    <h2 class="text-center">All Users</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Private Key</th>
                <th>Referrer ID</th>
                <th>Referrer Code</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['random_string']); ?></td>
                    <td><?php echo htmlspecialchars($user['referrer_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['referral_code']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
require('footer.inc.php');
?>