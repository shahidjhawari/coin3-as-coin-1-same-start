<?php
require('top.php');
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch user-specific data
$stmt = $conn->prepare("SELECT * FROM rewards WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_rewards = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch the user's referral code
$stmt = $conn->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_referral = $stmt->get_result()->fetch_assoc();
$stmt->close();

$referral_code = $user_referral['referral_code'];
$referral_link = SITE_PATH . "/signup.php?referral=" . $referral_code;

// Fetch the user's transaction status
$stmt = $conn->prepare("SELECT status FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transaction_status_row = $stmt->get_result()->fetch_assoc();
$transaction_status = $transaction_status_row['status'] ?? null;
$stmt->close();

// Fetch the latest deposit status
$stmt = $conn->prepare("SELECT status FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposit_status_row = $stmt->get_result()->fetch_assoc();
$deposit_status = $deposit_status_row['status'] ?? null;
$stmt->close();

// Calculate the wallet balance (sum of accepted deposits)
$stmt = $conn->prepare("SELECT SUM(amount) AS wallet_balance FROM deposits WHERE user_id = ? AND status = 'accepted'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet_balance_row = $stmt->get_result()->fetch_assoc();
$wallet_balance = $wallet_balance_row['wallet_balance'] ?? 0;
$stmt->close();

// Handle form submission for new deposits
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = $_POST['amount'];
    $screenshot = $_FILES['screenshot']['name'];
    move_uploaded_file($_FILES['screenshot']['tmp_name'], 'upload/' . $screenshot);

    $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, screenshot, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("ids", $user_id, $amount, $screenshot);
    $stmt->execute();
    $stmt->close();

    // Update transaction status to pending when a new deposit is made
    $transaction_status = 'pending';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link href="assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link href="assets/css/nucleo-svg.css" rel="stylesheet" />
    <link id="pagestyle" href="assets/css/soft-ui-dashboard.css?v=1.0.7" rel="stylesheet" />
    <link href="css/own1.css" rel="stylesheet" />
    <title>Dashboard</title>
</head>

<body>
    <div class="g-sidenav-show bg-gray-100">
        <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3" id="sidenav-main">
            <div class="sidenav-header">
                <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
                <a class="navbar-brand m-0" href="dashboard.php">
                    <img src="assets/img/logo-ct-dark.png" class="navbar-brand-img h-100" alt="main_logo">
                    <span class="ms-1 font-weight-bold">Stacking HUB</span>
                </a>
            </div>
            <hr class="horizontal dark mt-0">
            <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <span class="nav-link-text ms-1">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="staking.php">
                            <span class="nav-link-text ms-1">Stacking</span>
                        </a>
                    </li>
                    <!-- Other nav items -->
                </ul>
            </div>
        </aside>
        <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
            <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" navbar-scroll="true">
                <div class="container-fluid py-1 px-3">
                    <nav aria-label="breadcrumb">
                        <h6 class="font-weight-bolder mb-0">Dashboard</h6>
                    </nav>
                    <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                        <div class="ms-md-auto pe-md-3 d-flex align-items-center"></div>
                        <ul class="navbar-nav justify-content-end">
                            <li class="nav-item d-flex align-items-center">
                                <a href="logout.php" class="nav-link text-body font-weight-bold px-0">
                                    <i class="fa fa-user me-sm-1"></i>
                                    <span class="d-sm-inline d-none">Logout</span>
                                </a>
                            </li>
                            <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                                <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                                    <div class="sidenav-toggler-inner">
                                        <i class="sidenav-toggler-line"></i>
                                        <i class="sidenav-toggler-line"></i>
                                        <i class="sidenav-toggler-line"></i>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item px-3 d-flex align-items-center">
                                <a href="javascript:;" class="nav-link text-body p-0">
                                    <i class="fa fa-cog fixed-plugin-button-nav cursor-pointer"></i>
                                </a>
                            </li>
                            <li class="nav-item dropdown pe-2 d-flex align-items-center">
                                <a href="javascript:;" class="nav-link text-body p-0" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa fa-bell cursor-pointer"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="dropdownMenuButton">
                                    <!-- Dropdown items -->
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <!-- End Navbar -->
            <div class="container-fluid py-4">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-12">
                                        <h3>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h3>
                                        <p>Your Reward Points: <?php echo htmlspecialchars($user_rewards['reward_points']); ?></p>
                                        <p>Referral Count: <?php echo htmlspecialchars($user_rewards['referral_count']); ?></p>
                                        <p>Level One Count: <?php echo htmlspecialchars($user_rewards['level_one_count']); ?></p>
                                        <p>Level Two Count: <?php echo htmlspecialchars($user_rewards['level_two_count']); ?></p>
                                        <p>Level Three Count: <?php echo htmlspecialchars($user_rewards['level_three_count']); ?></p>
                                        <p><?php echo $referral_link ?></p>
                                        <?php if ($transaction_status === 'rejected') : ?>
                                            <p>Transaction Status: <?php echo htmlspecialchars($transaction_status); ?></p>
                                            <p><a href="activate.php" class="btn btn-info">Resend Activation Request</a></p>
                                        <?php elseif ($transaction_status === 'pending') : ?>
                                            <p>Transaction Status: <?php echo htmlspecialchars($transaction_status); ?></p>
                                        <?php elseif (!$transaction_status) : ?>
                                            <p><a href="activate.php" class="btn btn-info">Activate Account</a></p>
                                        <?php elseif ($transaction_status === 'accepted') : ?>
                                            <p>Transaction Status: <?php echo htmlspecialchars($transaction_status); ?></p>
                                            <p><a href="deposit.php" class="btn btn-info">Deposit</a></p>
                                        <?php endif; ?>
                                        <?php if ($transaction_status === 'accepted' && $deposit_status) : ?>
                                            <p>Deposit Status: <?php echo htmlspecialchars($deposit_status); ?></p>
                                        <?php endif; ?>
                                        <p>Wallet Balance (Amount): $<?php echo htmlspecialchars(number_format($wallet_balance, 2)); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Other content rows -->
                </div>
            </div>
        </main>
    </div>

    <?php require('footer.php'); ?>
</body>

</html>
