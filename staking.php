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
        // Calculate the new total staking amount
        $stmt = $conn->prepare("SELECT SUM(amount) AS total_staking FROM stakings WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $total_staking_amount = $stmt->get_result()->fetch_assoc()['total_staking'] ?? 0;
        $stmt->close();

        $total_staking_amount += $stake_amount;

        // Insert staking record
        $stmt = $conn->prepare("INSERT INTO stakings (user_id, amount, total_staking, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("idd", $user_id, $stake_amount, $total_staking_amount);
        $stmt->execute();
        $stmt->close();

        // Deduct the staked amount from the user's balance
        $stmt = $conn->prepare("UPDATE deposits SET amount = amount - ? WHERE user_id = ? AND status = 'accepted' AND amount >= ?");
        $stmt->bind_param("dii", $stake_amount, $user_id, $stake_amount);
        $stmt->execute();
        $stmt->close();

        // Recalculate the wallet balance
        $stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wallet_balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
        $stmt->close();

        $success_message = "Successfully staked $" . htmlspecialchars(number_format($stake_amount, 2));

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
        <button type="submit" class="btn btn-primary">Stake</button>
    </form>

    <h3>Staking Records</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Stake ID</th>
                <th>Amount</th>
                <th>Total Staking</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($staking_records as $record) : ?>
                <tr>
                    <td><?php echo $record['id']; ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['amount'], 2)); ?></td>
                    <td><?php echo htmlspecialchars(number_format($record['total_staking'], 2)); ?></td>
                    <td><?php echo htmlspecialchars($record['status']); ?></td>
                    <td><?php echo $record['created_at']; ?></td>
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
                <th>Staking Amount</th>
                <th>Daily Earning</th>
                <th>Withdraw</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
                <tr>
                    <td><!--Here the id of the daily earning from any staking has to be given--></td>
                    <td><!--Here Daily Earning will have its unique ID--></td>
                    <td><!--Here whatever amount is staked will show the daily earning--></td>
                    <!-- <td>Here he will show his daily earnings from whatever he has staked and the first day's earnings is 0.45%.--></td> 
                    <td><!--Here there will be a claim button, on clicking on which all the daily earnings of Sari will be transferred to the wallet balance and deducted from there.--></td>
                    <td><!--Here the date of Daily Earning will be shown on the date on which Daily Earning has started--></td>
                </tr>
        </tbody>


    <!-- And then the second day will be earned in the same way, But this time the percentage will be 0.55 percent -->
        <tbody>
                <tr>
                    <td><!--Here the id of the daily earning from any staking has to be given--></td>
                    <td><!--Here Daily Earning will have its unique ID--></td>
                    <td><!--Here whatever amount is staked will show the daily earning--></td>
                    <!-- <td>Here he will show his daily earnings from whatever he has staked and the first day's earnings is 0.55%.--></td> 
                    <td><!--Here there will be a claim button, on clicking on which all the daily earnings of Sari will be transferred to the wallet balance and deducted from there.--></td>
                    <td><!--Here the date of Daily Earning will be shown on the date on which Daily Earning has started--></td>
                </tr>
        </tbody>


        <!-- And then the third day will be earned in the same way, But this time the percentage will be 0.65 percent -->
        <tbody>
                <tr>
                    <td><!--Here the id of the daily earning from any staking has to be given--></td>
                    <td><!--Here Daily Earning will have its unique ID--></td>
                    <td><!--Here whatever amount is staked will show the daily earning--></td>
                    <!-- <td>Here he will show his daily earnings from whatever he has staked and the first day's earnings is 0.65%.--></td> 
                    <td><!--Here there will be a claim button, on clicking on which all the daily earnings of Sari will be transferred to the wallet balance and deducted from there.--></td>
                    <td><!--Here the date of Daily Earning will be shown on the date on which Daily Earning has started--></td>
                </tr>
        </tbody>
    </table>

    <!-- And then it will be repeated in the same way until its triple reward is fulfilled. For example, if the user invested 10 dollars on staking, he will get 30 dollars, that is, through triple daily earning. -->

    <h3>Total Staking Amount</h3>
    <p>Total staking amount: $<?php echo htmlspecialchars(number_format($total_staking_amount, 2)); ?></p>
    <h3>Total Estimated Earnign</h3>
    <p>Total Estimated Earnign: <!--Here it will show the estimated earning that how much he has to earn, for example if the user has invested 10 dollars, then he will get 30 dollars, then 30 dollars will be shown here.--> </p>
    <h3>Total Remaining Earnign</h3>
    <p>Total Remaining Earnign: <!--Here, the remaining earnings will be shown to the user out of three times, that is, as much as he has earned, what is left will be shown to him. If something has been done through it, then the remaining earnings will be shown here..--> </p>
</div>
