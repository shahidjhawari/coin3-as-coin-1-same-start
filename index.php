<?php
require('top.php');
session_start();

$showRandomKeyField = !(isset($_SESSION['viewed_key']) && $_SESSION['viewed_key']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $email = test_input($_POST["email"]);
    $password = test_input($_POST["password"]);
    $random_string = $showRandomKeyField ? test_input($_POST["random_string"]) : null;

    $stmt = $conn->prepare("SELECT id, password, random_string, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    $emailError = "";
    $passwordError = "";
    $randomStringError = "";

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $stored_random_string, $name);
        $stmt->fetch();

        if (!password_verify($password, $hashed_password)) {
            $passwordError = "Invalid password.";
        }

        if ($showRandomKeyField && $random_string !== $stored_random_string) {
            $randomStringError = "Invalid random key.";
        }

        if (empty($passwordError) && empty($randomStringError)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['random_string'] = $stored_random_string;
            $_SESSION['viewed_key'] = true;
            $_SESSION['login_time'] = time(); // Store the login time
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $emailError = "Invalid email.";
    }
    $stmt->close();
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

    .error-message {
        color: red;
        margin-top: 10px;
    }
</style>

<div class="container">
    <div class="centered-form">
        <div class="form-container">
            <div class="text-center mb-4">
                <img src="img/logo.png" alt="Logo" class="img-fluid" width="300">
            </div>
            <?php
            if (!empty($emailError)) {
                echo '<p class="error-message">' . $emailError . '</p>';
            }
            if (!empty($passwordError)) {
                echo '<p class="error-message">' . $passwordError . '</p>';
            }
            if (!empty($randomStringError)) {
                echo '<p class="error-message">' . $randomStringError . '</p>';
            }
            ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                </div>
                <?php if ($showRandomKeyField) : ?>
                    <div class="form-group">
                        <label for="random_string">Random Key *</label>
                        <input type="text" class="form-control" id="random_string" name="random_string" placeholder="Enter random key">
                    </div>
                <?php endif; ?>
                <div class="form-group text-right">
                    <a href="#" class="text-decoration-none">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="signup.php" class="text-decoration-none">Sign up</a></p>
            </div>
        </div>
    </div>
</div>

<?php require('footer.php'); ?>
