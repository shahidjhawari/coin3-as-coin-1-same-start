<?php
ob_start();
session_start();
require('header.php');

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stake_amount = $_POST['stake_amount'];

    if ($stake_amount > 0 && $stake_amount <= $wallet_balance) {
        // Insert staking record
        $stmt = $conn->prepare("INSERT INTO stakings (user_id, amount) VALUES (?, ?)");
        $stmt->bind_param("id", $user_id, $stake_amount);
        $stmt->execute();
        $staking_id = $stmt->insert_id;  // Get the inserted staking ID
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

    $now = new DateTime();
    $last_update = new DateTime($staking['last_earning_update']);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staking</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
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

        <h3>Staking Records</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Stake ID</th>
                    <th>Amount</th>
                    <th>Total Earned</th>
                    <th>Status</th>
                    <th>Start Date</th>
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
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Daily Earning Records</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Stake ID</th>
                    <th>Daily Earning ID</th>
                    <th>Daily Earning</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($staking_records as $record) {
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
                }
                ?>
            </tbody>
        </table>

        <h3>Total Staking Amount</h3>
        <p>Total staking amount: $<?php echo htmlspecialchars(number_format($total_staking_amount, 2)); ?></p>

        <h3>Total Estimated Earning</h3>
        <p>Total Estimated Earning: <!-- Here it will show the estimated earning that how much he has to earn, for example if the user has invested 10 dollars, then he will get 30 dollars, then 30 dollars will be shown here. --> </p>

        <h3>Total Remaining Earning</h3>
        <p>Total Remaining Earning: <!-- Here, the remaining earnings will be shown to the user out of three times, that is, as much as he has earned, what is left will be shown to him. If something has been done through it, then the remaining earnings will be shown here.. --> </p>
    </div>
</body>
</html>
