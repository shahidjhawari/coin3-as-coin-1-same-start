<?php
ob_start();
session_start();
require('header.php');

date_default_timezone_set('Asia/Karachi');

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the user's current balance
$stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet_balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
$stmt->close();

// Initialize variables for success and error messages
$success_message = $error_message = "";

// Handle the staking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stake_amount'])) {
    $stake_amount = $_POST['stake_amount'];

    if ($stake_amount > 0 && $stake_amount <= $wallet_balance) {
        // Insert staking record
        $stmt = $conn->prepare("INSERT INTO stakings (user_id, amount) VALUES (?, ?)");
        $stmt->bind_param("id", $user_id, $stake_amount);
        $stmt->execute();
        $staking_id = $stmt->insert_id;
        $stmt->close();

        // Deduct the staked amount from the user's balance
        $stmt = $conn->prepare("UPDATE deposits SET amount = amount - ? WHERE user_id = ? AND status = 'accepted' LIMIT 1");
        $stmt->bind_param("di", $stake_amount, $user_id);
        $stmt->execute();
        $stmt->close();

        // Calculate the first daily earning (0.45%)
        $first_earning = $stake_amount * 0.0045;
        $stmt = $conn->prepare("INSERT INTO daily_earnings (staking_id, earning) VALUES (?, ?)");
        $stmt->bind_param("id", $staking_id, $first_earning);
        $stmt->execute();
        $stmt->close();

        // Update staking record with first earning
        $stmt = $conn->prepare("UPDATE stakings SET total_earned = ?, last_earning_update = NOW() WHERE id = ?");
        $stmt->bind_param("di", $first_earning, $staking_id);
        $stmt->execute();
        $stmt->close();

        // Recalculate the wallet balance
        $stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wallet_balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
        $stmt->close();

        $success_message = "Successfully staked $" . htmlspecialchars(number_format($stake_amount, 2)) . " and earned $" . htmlspecialchars(number_format($first_earning, 2));
    } else {
        $error_message = "Invalid staking amount.";
    }
}

// Handle the claim earnings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_earnings'])) {
    $stmt = $conn->prepare("SELECT id, total_earned, claimed FROM stakings WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($staking = $result->fetch_assoc()) {
        $earnings_to_claim = $staking['total_earned'] - $staking['claimed'];

        if ($earnings_to_claim > 0) {
            // Update the staking record to mark the earnings as claimed
            $stmt_update = $conn->prepare("UPDATE stakings SET claimed = claimed + ? WHERE id = ?");
            $stmt_update->bind_param("di", $earnings_to_claim, $staking['id']);
            $stmt_update->execute();
            $stmt_update->close();

            // Add the earnings to the user's wallet balance
            $stmt_update = $conn->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, 'accepted')");
            $stmt_update->bind_param("id", $user_id, $earnings_to_claim);
            $stmt_update->execute();
            $stmt_update->close();
        }
    }
    $stmt->close();

    // Recalculate the wallet balance
    $stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wallet_balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
    $stmt->close();

    $success_message = "All daily earnings have been claimed.";
}

// Fetch staking records and calculate earnings
$staking_records = [];
$stmt = $conn->prepare("SELECT * FROM stakings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $staking_records[] = $row;
}
$stmt->close();

$total_staking_amount = 0;
foreach ($staking_records as &$record) {
    $total_staking_amount += $record['amount'];
    calculateAndUpdateEarnings($record);
}

// Function to calculate and update earnings
function calculateAndUpdateEarnings(&$staking) {
    global $conn;

    $now = new DateTime("now", new DateTimeZone('Asia/Karachi'));
    $last_update = new DateTime($staking['last_earning_update'], new DateTimeZone('Asia/Karachi'));
    $interval = $last_update->diff($now);
    $days_passed = $interval->days;

    if ($days_passed > 0 && $staking['status'] === 'active') {
        $daily_rates = [0.0045, 0.0055, 0.0065];
        $total_earned = 0;
        for ($i = 0; $i < $days_passed; $i++) {
            $rate_index = ($i % 3);
            $earning = $staking['amount'] * $daily_rates[$rate_index];
            $total_earned += $earning;

            // Insert daily earning record
            $stmt = $conn->prepare("INSERT INTO daily_earnings (staking_id, earning) VALUES (?, ?)");
            $stmt->bind_param("id", $staking['id'], $earning);
            $stmt->execute();
            $stmt->close();
        }

        $staking['total_earned'] += $total_earned;

        // Update staking record
        $stmt = $conn->prepare("UPDATE stakings SET total_earned = ?, last_earning_update = NOW() WHERE id = ?");
        $stmt->bind_param("di", $staking['total_earned'], $staking['id']);
        $stmt->execute();
        $stmt->close();

        // Stop staking if earnings reach triple the staked amount
        if ($staking['total_earned'] >= 3 * $staking['amount']) {
            $stmt = $conn->prepare("UPDATE stakings SET status = 'completed' WHERE id = ?");
            $stmt->bind_param("i", $staking['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}


// Calculate total estimated and remaining earnings
$total_estimated_earning = $total_staking_amount * 3;
$total_remaining_earning = $total_estimated_earning;
foreach ($staking_records as $record) {
    $total_remaining_earning -= $record['total_earned'];
}

function calculateUnclaimedEarnings($user_id) {
    global $conn;
    $total_unclaimed_earnings = 0;

    $stmt = $conn->prepare("SELECT total_earned, claimed FROM stakings WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($staking = $result->fetch_assoc()) {
        $total_unclaimed_earnings += ($staking['total_earned'] - $staking['claimed']);
    }

    $stmt->close();
    return $total_unclaimed_earnings;
}

// Calculate unclaimed earnings
$total_unclaimed_earnings = calculateUnclaimedEarnings($user_id);

?>

    <div class="container">
        <h2>Staking</h2>
        <p>Wallet Balance: $<?php echo htmlspecialchars(number_format($wallet_balance, 2)); ?></p>

        <?php if (!empty($success_message)) : ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)) : ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="staking.php">
            <div class="form-group">
                <label for="stake_amount">Amount to Stake:</label>
                <input type="number" class="form-control" id="stake_amount" name="stake_amount" step="0.01" min="5" max="<?php echo htmlspecialchars($wallet_balance); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Stake</button>
        </form>

        <form method="post" action="staking.php">
            <button type="submit" name="claim_earnings" class="btn btn-success mt-3" <?php echo ($total_unclaimed_earnings <= 0) ? 'disabled' : ''; ?>>Claim Now</button>
        </form>

        <h3>Staking Records</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Stake ID</th>
                    <th>Amount</th>
                    <th>Total Earned</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staking_records as $record) : ?>
                    <tr>
                        <td><?php echo $record['id']; ?></td>
                        <td><?php echo htmlspecialchars(number_format($record['amount'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($record['total_earned'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($record['status']); ?></td>
                        <td><?php echo $record['start_time']; ?></td>
                    </tr>
                    <?php
                    // Fetch daily earnings for this staking record
                    $stmt = $conn->prepare("SELECT * FROM daily_earnings WHERE staking_id = ?");
                    $stmt->bind_param("i", $record['id']);
                    $stmt->execute();
                    $earnings = $stmt->get_result();
                    while ($earning = $earnings->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . $record['id'] . '</td>';
                        echo '<td>' . $earning['id'] . '</td>';
                        echo '<td>' . htmlspecialchars(number_format($earning['earning'], 2)) . '</td>';
                        echo '<td>' . $earning['date'] . '</td>';
                        echo '</tr>';
                    }
                    $stmt->close();
                ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Total Staking Amount</h3>
        <p>Total staking amount: $<?php echo htmlspecialchars(number_format($total_staking_amount, 2)); ?></p>

        <h3>Total Estimated Earning</h3>
        <p>Total Estimated Earning: $<?php echo htmlspecialchars(number_format($total_estimated_earning, 2)); ?></p>

        <h3>Total Remaining Earning</h3>
        <p>Total Remaining Earning: $<?php echo htmlspecialchars(number_format($total_remaining_earning, 2)); ?></p>
    </div>
