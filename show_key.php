<?php
require('top.php');
$_SESSION['viewed_key'] = true;

function test_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
<style>
    body {
        background: #070F2B;
        color: white;
        font-family: Arial, sans-serif;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
    }

    .container {
        text-align: center;
        background: #141E46;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        max-width: 100%;
        overflow-x: auto;
    }

    .random-string {
        font-size: 20px;
        margin-top: 10px;
        color: #FFD700;
        word-wrap: break-word;
    }

    .copy-button {
        margin-top: 20px;
    }

    .copy-message {
        display: none;
        color: white;
        margin-top: 10px;
    }
</style>

<div class="container">
    <h1 class="mb-4" style="font-size: 20px;">Your Private Key</h1>
    <p style="font-size: 13px; margin-top: -20px ;">Please save your key in a safe place. If you do not have this key, you will not be able to log into your account again.</p>
    <p class="random-string" style="font-size: 15px;">
        <?php
        if (isset($_GET['random_string'])) {
            echo htmlspecialchars($_GET['random_string']);
        } else {
            echo "No random string found.";
        }
        ?>
    </p>
    <button class="btn btn-warning copy-button" onclick="copyRandomString()"><b>Copy Private Key</b></button><br><br>
    <span class="copy-message" id="copyMessage">Your Key has been copied</span>

    <div class="mt-4">
        <button class="btn btn-secondary mr-2" onclick="goBack()"><b>Back</b></button>
        <button class="btn btn-primary" onclick="goToLogin()"><b>Login</b></button>
    </div>
</div>

<script>
    function copyRandomString() {
        var randomString = document.querySelector('.random-string');
        var range = document.createRange();
        range.selectNode(randomString);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand("copy");
        window.getSelection().removeAllRanges();
        var copyMessage = document.getElementById('copyMessage');
        copyMessage.style.display = 'inline';
        setTimeout(function() {
            copyMessage.style.display = 'none';
        }, 2000);
    }

    function goBack() {
        window.location.href = "signup.php";
    }

    function goToLogin() {
        window.location.href = "index.php";
    }
</script>

<?php require('footer.php') ?>