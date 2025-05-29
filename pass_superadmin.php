<?php
// Include database connection
include('../config/database.php'); // Make sure the path is correct
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

// Start the session only if it hasn't been started yet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in as a superadmin
if (!isset($_SESSION['account_number']) || $_SESSION['role'] !== 'superadmin') {
    echo "Access denied.";
    exit;
}

// Password verification: the logged-in superadmin's password and the current superadmin's password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if both the logged-in superadmin's password and the current superadmin's password were submitted
    if (isset($_POST['current_account_password']) && !empty($_POST['current_account_password']) && isset($_POST['current_superadmin_password']) && !empty($_POST['current_superadmin_password'])) {
        $current_account_password_input = $_POST['current_account_password'];
        $current_superadmin_password_input = $_POST['current_superadmin_password'];

        // Check the logged-in superadmin's password
        $stmt = $db->prepare("SELECT * FROM users WHERE account_number = :account_number AND role = 'superadmin'");
        $stmt->execute(['account_number' => $_SESSION['account_number']]);
        $user_data = $stmt->fetch();

        // Verify if the logged-in superadmin's password is correct
        if ($user_data && password_verify($current_account_password_input, $user_data['password'])) {

            // Check the current superadmin's password
            $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
            $stmt->execute();
            $superadmin_data = $stmt->fetch();

            // Verify if the current superadmin's password is correct
            if (password_verify($current_superadmin_password_input, $superadmin_data['password_hash'])) {
                // If both passwords are correct, proceed with changes

                // Change the admin password
                if (isset($_POST['new_admin_password']) && !empty($_POST['new_admin_password'])) {
                    $new_admin_password = password_hash($_POST['new_admin_password'], PASSWORD_DEFAULT);

                    // Apply the password change for the admin
                    $stmt = $db->prepare("UPDATE admin_passwords SET password_hash = ? WHERE id = 1");
                    $stmt->execute([$new_admin_password]);

                    echo "The admin password has been successfully changed.";
                }

                // Change the superadmin password
                if (isset($_POST['new_superadmin_password']) && !empty($_POST['new_superadmin_password'])) {
                    $new_superadmin_password = password_hash($_POST['new_superadmin_password'], PASSWORD_DEFAULT);

                    // Apply the password change for the superadmin
                    $stmt = $db->prepare("UPDATE superadmin_passwords SET password_hash = ? WHERE id = 1");
                    $stmt->execute([$new_superadmin_password]);

                    echo "The superadmin password has been successfully changed.";
                }
            } else {
                // If the current superadmin's password is incorrect
                echo "The current superadmin password is incorrect.";
                exit; // Stop execution and prevent the page from being displayed
            }
        } else {
            // If the logged-in superadmin's password is incorrect
            echo "The logged-in superadmin's password is incorrect.";
            exit; // Stop execution and prevent the page from being displayed
        }
    }
}

// If the user is not authenticated yet, display a form for the passwords
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['current_account_password']) || !isset($_POST['current_superadmin_password'])) {
    echo '<form method="POST" action="">
            <label for="current_account_password">Current Superadmin Account Password:</label>
            <input type="password" name="current_account_password" required><br><br>

            <label for="current_superadmin_password">Current Superadmin Password:</label>
            <input type="password" name="current_superadmin_password" required><br><br>

            <button type="submit">Submit</button>
          </form>';
    exit; // Stop execution and prevent the page from being displayed
}
?>

<!-- Password change form -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Change</title>
</head>
<body>
    <h2>Change Password</h2>
    <?php include('navigation.php'); ?>
    <form method="POST">
        <!-- Field for the current superadmin password -->
        <label for="current_superadmin_password">Current Superadmin Password:</label>
        <input type="password" name="current_superadmin_password" required><br><br>

        <!-- Field for the new admin password -->
        <label for="new_admin_password">New Admin Password:</label>
        <input type="password" name="new_admin_password"><br><br>

        <!-- Field for the new superadmin password -->
        <label for="new_superadmin_password">New Superadmin Password:</label>
        <input type="password" name="new_superadmin_password"><br><br>

        <input type="submit" value="Change Password">
    </form>
</body>
</html>
