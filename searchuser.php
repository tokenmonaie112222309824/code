<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Role-based protection
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: register.php");
    exit;
}

// Admin authentication before proceeding
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        $stmt = $db->prepare("SELECT * FROM admin_passwords LIMIT 1");
        $stmt->execute();
        $adminPassword = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($_POST['admin_password'], $adminPassword['password_hash'])) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        echo '<h2>Admin Authentication Required</h2>';
        if (isset($error)) echo "<p style='color:red;'>$error</p>";
        echo '<form method="POST">
                <input type="password" name="admin_password" placeholder="Admin password" required>
                <button type="submit">Access</button>
              </form>';
        exit;
    }
}

// Handling the search request
$userData = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search = trim($_POST['search']);

    if ($_SESSION['role'] === 'admin') {
        // Search for a user only (not admin/superadmin)
        $stmt = $db->prepare("SELECT id, pseudo, secret_pseudo, code_pin, account_number, created_at, wallet_balance 
                              FROM users 
                              WHERE (id = :search OR pseudo = :search OR secret_pseudo = :search OR account_number = :search)
                              AND role = 'user'");
    } elseif ($_SESSION['role'] === 'superadmin') {
        // Search for a user only (not admin/superadmin)
        $stmt = $db->prepare("SELECT id, pseudo, secret_pseudo, code_pin, account_number, created_at, wallet_balance 
                              FROM users 
                              WHERE (id = :search OR pseudo = :search OR secret_pseudo = :search OR account_number = :search)
                              AND role = 'user'");
    }

    $stmt->execute(['search' => $search]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handling account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $account_number_to_delete = $_POST['account_number'];

    // Ask for the admin password to validate deletion
    if (isset($_POST['admin_password_for_delete']) && !empty($_POST['admin_password_for_delete'])) {
        // Verify the admin password
        $stmt = $db->prepare("SELECT * FROM admin_passwords LIMIT 1");
        $stmt->execute();
        $adminPassword = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($_POST['admin_password_for_delete'], $adminPassword['password_hash'])) {
            // If the admin password is correct, delete the account
            if ($_SESSION['role'] === 'admin') {
                $stmt = $db->prepare("DELETE FROM users WHERE account_number = ? AND role = 'user'");
                $stmt->execute([$account_number_to_delete]);
            } elseif ($_SESSION['role'] === 'superadmin') {
                $stmt = $db->prepare("DELETE FROM users WHERE account_number = ?");
                $stmt->execute([$account_number_to_delete]);
            }
            echo "<p style='color:green;'>The account has been successfully deleted.</p>";
        } else {
            echo "<p style='color:red;'>Incorrect admin password.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Search</title>
</head>
<body>

    <h1>User Search</h1>
    <?php include('navigation.php'); ?>
    <form method="POST">
        <input type="text" name="search" placeholder="ID, pseudo, secret_pseudo or account number" required>
        <button type="submit">Search</button>
    </form>

    <?php if ($userData): ?>
        <h2>User Information:</h2>
        <ul>
            <li><strong>ID:</strong> <?= htmlspecialchars($userData['id']) ?></li>
            <li><strong>Pseudo:</strong> <?= htmlspecialchars($userData['pseudo']) ?></li>
            <li><strong>Secret Pseudo:</strong> <?= htmlspecialchars($userData['secret_pseudo']) ?></li>
            <li><strong>PIN Code:</strong> <?= htmlspecialchars($userData['code_pin']) ?></li>
            <li><strong>Account Number:</strong> <?= htmlspecialchars($userData['account_number']) ?></li>
            <li><strong>Account Created On:</strong> <?= htmlspecialchars($userData['created_at']) ?></li>
            <li><strong>Balance:</strong> <?= htmlspecialchars($userData['wallet_balance']) ?> V</li>
        </ul>

        <form method="POST" style="display:inline;">
            <input type="hidden" name="account_number" value="<?= htmlspecialchars($userData['account_number']) ?>">
            <input type="password" name="admin_password_for_delete" placeholder="Admin password for deletion" required>
            <button type="submit" name="delete_account">Delete this account</button>
        </form>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
        <p style="color:red;">No user found or you cannot search for an admin/superadmin.</p>
    <?php endif; ?>

</body>
</html>
