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

// Fetch the user's current balance and random string
$stmt = $conn->prepare("SELECT SUM(deposits.amount) AS wallet_balance, users.random_string 
                        FROM deposits 
                        JOIN users ON deposits.user_id = users.id 
                        WHERE deposits.user_id = ? AND deposits.status = 'accepted'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$wallet_balance = $result['wallet_balance'] ?? 0;
$stored_random_string = $result['random_string'];
$stmt->close();

// Initialize variables for success and error messages
$success_message = $error_message = "";

// Function to calculate earnings based on the day number
function calculate_daily_earning($day, $amount)
{
    $percentages = [0.0045, 0.0055, 0.0065];
    return $amount * $percentages[$day % 3];
}

// Handle the staking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stake_amount'], $_POST['random_string'])) {
    $stake_amount = $_POST['stake_amount'];
    $input_random_string = $_POST['random_string'];

    if ($input_random_string !== $stored_random_string) {
        $error_message = "You have provided an incorrect random string.";
    } elseif ($stake_amount > 0 && $stake_amount <= $wallet_balance) {
        $estimated_earning = 3 * $stake_amount;
        $remaining_earning = $estimated_earning;

        // Insert staking record
        $stmt = $conn->prepare("INSERT INTO stakings (user_id, amount, estimated_earning, remaining_earning, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("iddd", $user_id, $stake_amount, $estimated_earning, $remaining_earning);
        $stmt->execute();
        $staking_id = $stmt->insert_id; // Get the ID of the newly inserted staking record
        $stmt->close();

        // Deduct the staked amount from the user's balance
        $stmt = $conn->prepare("UPDATE deposits SET amount = amount - ? WHERE user_id = ? AND status = 'accepted' AND amount >= ?");
        $stmt->bind_param("dii", $stake_amount, $user_id, $stake_amount);
        $stmt->execute();
        $stmt->close();

        // Calculate the first day's earnings and insert the record
        $first_daily_earning = calculate_daily_earning(0, $stake_amount);
        $stmt = $conn->prepare("INSERT INTO daily_earnings (user_id, staking_id, date, amount) VALUES (?, ?, CURDATE(), ?)");
        $stmt->bind_param("iid", $user_id, $staking_id, $first_daily_earning);
        $stmt->execute();
        $stmt->close();

        // Update total earned and remaining earning in stakings table
        $remaining_earning -= $first_daily_earning;
        $stmt = $conn->prepare("UPDATE stakings SET total_earning = total_earning + ?, remaining_earning = ? WHERE id = ?");
        $stmt->bind_param("dii", $first_daily_earning, $remaining_earning, $staking_id);
        $stmt->execute();
        $stmt->close();

        // Recalculate the wallet balance
        $stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wallet_balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
        $stmt->close();

        $success_message = "Successfully staked $" . htmlspecialchars(number_format($stake_amount, 2)) . " and earned $" . htmlspecialchars(number_format($first_daily_earning, 2)) . " on the first day.";

        // Redirect to prevent form resubmission
        header("Location: staking.php");
        exit(); // Ensure script termination after redirection
    } else {
        $error_message = "Invalid staking amount.";
    }
}

// Fetch staking records
$staking_records = [];
$stmt = $conn->prepare("SELECT * FROM stakings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $staking_records[] = $row;
}
$stmt->close();

// Calculate and update daily earnings for active stakings
$today = new DateTime();
foreach ($staking_records as &$staking) {
    if ($staking['status'] == 'active' && !$staking['is_tripled']) {
        $start_date = new DateTime($staking['created_at']);
        $interval = $start_date->diff($today)->days;

        // Skip Sundays
        if ($today->format('N') != 7 && $interval > 0) { // Ensure not to recalculate the first day's earnings
            $daily_earning = calculate_daily_earning($interval, $staking['amount']);
            $staking['total_earning'] += $daily_earning;
            $staking['remaining_earning'] -= $daily_earning;

            // Insert daily earning record
            $stmt = $conn->prepare("INSERT INTO daily_earnings (user_id, staking_id, date, amount) VALUES (?, ?, CURDATE(), ?)");
            $stmt->bind_param("iid", $user_id, $staking['id'], $daily_earning);
            $stmt->execute();
            $stmt->close();

            // Update total earned and check if tripled
            $is_tripled = $staking['total_earning'] >= 3 * $staking['amount'];
            $stmt = $conn->prepare("UPDATE stakings SET total_earning = ?, remaining_earning = ?, is_tripled = ? WHERE id = ?");
            $stmt->bind_param("ddii", $staking['total_earning'], $staking['remaining_earning'], $is_tripled, $staking['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Fetch daily earnings records
$daily_earnings_records = [];
$stmt = $conn->prepare("SELECT * FROM daily_earnings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $daily_earnings_records[] = $row;
}
$stmt->close();

// Calculate total staking amount
$total_staking_amount = 0;
foreach ($staking_records as $record) {
    $total_staking_amount += $record['amount'];
}
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
        <div class="form-group">
            <label for="random_string">Random String:</label>
            <input type="text" class="form-control" id="random_string" name="random_string" required>
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
                <th>Estimated Earning</th>
                <th>Remaining Earning</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($staking_records as $record) : ?>
                <tr>
                    <td><?php echo $record['id']; ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['amount'], 2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['total_earning'], 2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['estimated_earning'], 2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['remaining_earning'], 2)); ?></td>
                    <td><?php echo $record['is_tripled'] ? 'Completed' : 'Active'; ?></td>
                    <td><?php echo $record['created_at']; ?></td>
                    <td>
                        <?php if ($record['is_tripled']) : ?>
                            <form method="post" action="withdraw.php">
                                <input type="hidden" name="stake_id" value="<?php echo $record['id']; ?>">
                                <button type="submit" class="btn btn-success">Withdraw</button>
                            </form>
                        <?php else : ?>
                            <button type="button" class="btn btn-secondary" disabled>Withdraw</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Daily Earnings Records</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daily_earnings_records as $record) : ?>
                <tr>
                    <td><?php echo $record['date']; ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['amount'], 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Summary</h3>
    <p>Total Staking Amount: $<?php echo htmlspecialchars(number_format($total_staking_amount, 2)); ?></p>
    <p>Total Earned: $<?php echo htmlspecialchars(number_format(array_sum(array_column($staking_records, 'total_earning')), 2)); ?></p>
    <p>Total Earnings Remaining: $<?php echo htmlspecialchars(number_format((3 * $total_staking_amount) - array_sum(array_column($staking_records, 'total_earning')), 2)); ?></p>
</div>