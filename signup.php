<?php
require('top.php');

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateReferralCode($length = 8) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}

$emailError = "";
$passwordError = "";
$referralError = "";

// Check for referral code in URL
$referral_code = isset($_GET['referral']) ? test_input($_GET['referral']) : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = test_input($_POST["name"]);
    $email = test_input($_POST["email"]);
    $password = test_input($_POST["password"]);
    $confirmPassword = test_input($_POST["confirmPassword"]);
    $referral = test_input($_POST["referral"]);

    if ($password != $confirmPassword) {
        $passwordError = "Passwords do not match.";
    } else {
        // Check if the email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $emailError = "Email already exists.";
            $stmt->close();
        } else {
            $stmt->close();
            $referrer_id = null;

            if (!empty($referral)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->bind_param("s", $referral);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($referrer_id);
                    $stmt->fetch();
                    $stmt->close();
                } else {
                    $referralError = "Invalid referral code.";
                    $stmt->close();
                }
            }

            if (empty($referralError)) {
                $randomString = bin2hex(random_bytes(50));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $referral_code = generateReferralCode();

                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, random_string, referrer_id, referral_code) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $name, $email, $hashed_password, $randomString, $referrer_id, $referral_code);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    $stmt->close();

                    // Initialize rewards for the new user
                    $stmt = $conn->prepare("INSERT INTO rewards (user_id, reward_points, referral_count, level_one_count, level_two_count, level_three_count) VALUES (?, 0, 0, 0, 0, 0)");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();

                    // Reward the referrer
                    if ($referrer_id !== null) {
                        rewardReferrer($referrer_id, 10, 1);
                    }

                    header("Location: show_key.php?random_string=" . urlencode($randomString));
                    exit();
                } else {
                    echo "Error: " . $stmt->error;
                }
            }
        }
    }
}

function rewardReferrer($referrer_id, $points, $level) {
    global $conn;
    if ($level > 3) {
        return;
    }

    // Update rewards and level count for the current referrer
    if ($level == 1) {
        $stmt = $conn->prepare("UPDATE rewards SET reward_points = reward_points + ?, referral_count = referral_count + 1, level_one_count = level_one_count + 1 WHERE user_id = ?");
    } elseif ($level == 2) {
        $stmt = $conn->prepare("UPDATE rewards SET reward_points = reward_points + ?, level_two_count = level_two_count + 1 WHERE user_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE rewards SET reward_points = reward_points + ?, level_three_count = level_three_count + 1 WHERE user_id = ?");
    }
    $stmt->bind_param("ii", $points, $referrer_id);
    $stmt->execute();
    $stmt->close();

    if ($level < 3) {
        // Get the next level referrer
        $stmt = $conn->prepare("SELECT referrer_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $referrer_id);
        $stmt->execute();
        $stmt->bind_result($next_referrer_id);
        $stmt->fetch();
        $stmt->close();

        if ($next_referrer_id !== null) {
            // Determine points for the next level
            $next_points = ($level == 1) ? 5 : 2;
            rewardReferrer($next_referrer_id, $next_points, $level + 1);
        }
    }
}
?>

<style>
    body {
        background: #070F2B;
        color: white;
    }

    .centered-form {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .form-container {
        width: 100%;
        max-width: 400px;
        padding: 20px;
        border: 1px solid #e3e3e3;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        background: #141E46;
    }

    .error {
        color: red;
        margin-top: 5px;
    }
</style>

<div class="container">
    <div class="centered-form">
        <div class="form-container">
            <div class="text-center mb-4">
                <img src="img/logo.png" alt="Logo" class="img-fluid" width="300">
            </div>
            <form action="signup.php" method="post" autocomplete="off">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter your name" required autocomplete="new-name">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required autocomplete="new-email">
                    <span class="error"><?php echo $emailError; ?></span>
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required autocomplete="new-password">
                    <span class="error"><?php echo $passwordError; ?></span>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="referral">Referral Code (optional)</label>
                    <input type="text" class="form-control" id="referral" name="referral" placeholder="Enter referral code" value="<?php echo $referral_code; ?>">
                    <span class="error"><?php echo $referralError; ?></span>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
            </form>
            <div class="text-center mt-3">
                <p>Already have an account? <a href="index.php" class="text-decoration-none">Login</a></p>
            </div>
        </div>
    </div>
</div>

<?php require('footer.php'); ?>
