<?php
require('connection.inc.php'); // Include your database connection file

// Function to calculate earnings based on the day number
function calculate_daily_earning($day, $amount) {
    $percentages = [0.0045, 0.0055, 0.0065];
    return $amount * $percentages[$day % 3];
}

// Fetch all active stakings
$stmt = $conn->prepare("SELECT * FROM stakings WHERE status = 'active' AND is_tripled = FALSE");
$stmt->execute();
$stakings = $stmt->get_result();
$stmt->close();

foreach ($stakings as $staking) {
    $user_id = $staking['user_id'];
    $staking_id = $staking['id'];
    $amount = $staking['amount'];
    $total_earned = $staking['total_earned'];
    
    // Skip Sundays
    $today = new DateTime();
    if ($today->format('N') == 7) {
        continue;
    }

    // Calculate daily earning based on the day number since staking started
    $start_date = new DateTime($staking['created_at']);
    $interval = $start_date->diff($today)->days;
    $daily_earning = calculate_daily_earning($interval, $amount);
    
    // Insert daily earning record
    $stmt = $conn->prepare("INSERT INTO daily_earnings (user_id, staking_id, date, amount) VALUES (?, ?, CURDATE(), ?)");
    $stmt->bind_param("iid", $user_id, $staking_id, $daily_earning);
    $stmt->execute();
    $stmt->close();
    
    // Update total earned and check if tripled
    $total_earned += $daily_earning;
    $is_tripled = $total_earned >= 3 * $amount;
    $stmt = $conn->prepare("UPDATE stakings SET total_earned = ?, is_tripled = ? WHERE id = ?");
    $stmt->bind_param("dii", $total_earned, $is_tripled, $staking_id);
    $stmt->execute();
    $stmt->close();
}
?>
