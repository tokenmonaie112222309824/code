<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the account number is passed in the session
if (!isset($_SESSION['account_number'])) {
    echo "<div class='error-message'>No account number found. Please register again.</div>";
    exit;
}

$account_number = $_SESSION['account_number'];

// Handle account number validation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['validate_account'])) {
    $entered_account_number = $_POST['account_number'];

    // Check if the entered account number is correct
    if ($entered_account_number == $account_number) {
        echo "<div class='success-message'>Your account has been successfully confirmed!</div>";
        echo "<a href='login.php' class='button'>Log in</a>";
        // Once validated, destroy the session to avoid reusing the account number
        session_destroy();
    } else {
        echo "<div class='error-message'>The account number is incorrect. Please try again.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Confirmation</title>
    <link rel="stylesheet" href="../assets/css/confirm_account.css">
</head>
<body>
    <div class="container">
        <div class="header">
        <img src="../assets/logo.PNG" alt="Logo" class="logo">
            <h1>Account Confirmation</h1>
        </div>

        <div class="confirmation">
            <p>Your account has been successfully created!</p>
            <p>Here is your account number to copy and paste below:</p>
            <input type="text" value="<?php echo $account_number; ?>" readonly>
            <form method="POST">
                <input type="text" name="account_number" placeholder="Paste your account number here" required>
                <button type="submit" name="validate_account">Validate Account Number</button>
            </form>
        </div>
    </div>
</body>
</html>
