<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Verifying superadmin password on page load
if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify the superadmin password
        $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
        $stmt->execute();
        $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify the hashed password
        if (password_verify($_POST['password'], $passwordRow['password_hash'])) {
            $_SESSION['is_authenticated'] = true;
        } else {
            echo "Incorrect password.";
            exit;
        }
    }
    // Total number of users
    if ($_SESSION['role'] === 'admin') {
        $countStmt = $db->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin', 'superadmin')");
    } else {
        $countStmt = $db->query("SELECT COUNT(*) FROM users");
    }
    $totalUsers = $countStmt->fetchColumn();

    // If not authenticated, display the password form
    echo '<form method="POST">
            <label for="password">Superadmin Password:</label>
            <input type="password" id="password" name="password" required autocomplete="off">
            <button type="submit">Login</button>
          </form>';
    exit;
}

// Calculate user count
$countStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalUsers = $countStmt->fetchColumn();

// Filter users by search field (pseudo, account number, secret pseudo)
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Retrieve users only if a search term is provided
$users = [];
if ($searchTerm) {
    // Search across multiple fields with LIKE
    $query = "SELECT * FROM users WHERE role = 'user' AND (pseudo LIKE :searchTerm OR account_number LIKE :searchTerm OR secret_pseudo LIKE :searchTerm)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':searchTerm', '%' . $searchTerm . '%');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If a modification is made and superadmin password is provided
if (isset($_POST['user_id'])) {
    if (!isset($_POST['superadmin_password']) || $_POST['superadmin_password'] == '') {
        echo "Please enter the superadmin password to make this modification.";
        exit;
    }

    // Verify the superadmin password
    $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
    $stmt->execute();
    $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify the hashed password
    if (!password_verify($_POST['superadmin_password'], $passwordRow['password_hash'])) {
        echo "Incorrect password.";
        exit;
    }

    // Retrieve the data to be modified
    $user_id = $_POST['user_id'];
    $pseudo = $_POST['pseudo'] ?? null;
    $password = $_POST['password'] ?? null;
    $secret_pseudo = $_POST['secret_pseudo'] ?? null;
    $code_pin = $_POST['code_pin'] ?? null;
    $wallet_balance = $_POST['wallet_balance'] ?? null;

    // If a password is provided, hash it
    if ($password) {
        $password = password_hash($password, PASSWORD_BCRYPT);
    }

    // Update the user
    $updateStmt = $db->prepare("UPDATE users SET 
        pseudo = COALESCE(:pseudo, pseudo), 
        password = COALESCE(:password, password), 
        secret_pseudo = COALESCE(:secret_pseudo, secret_pseudo), 
        code_pin = COALESCE(:code_pin, code_pin), 
        wallet_balance = COALESCE(:wallet_balance, wallet_balance) 
        WHERE id = :id");
    $updateStmt->bindParam(':pseudo', $pseudo);
    $updateStmt->bindParam(':password', $password);
    $updateStmt->bindParam(':secret_pseudo', $secret_pseudo);
    $updateStmt->bindParam(':code_pin', $code_pin);
    $updateStmt->bindParam(':wallet_balance', $wallet_balance);
    $updateStmt->bindParam(':id', $user_id);
    $updateStmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Management - Superadmin</title>
</head>
<body>
<h1>Account Management (Users)</h1>

<?php include('navigation.php'); ?>
<p>Total number of users: <?= htmlspecialchars($totalUsers) ?></p>

<!-- Search form with one field -->
<form method="GET" action="">
    <label for="search">Search account (pseudo, account number, secret pseudo):</label>
    <input type="text" id="search" name="search" value="<?= htmlspecialchars($searchTerm) ?>" autocomplete="off">
    <button type="submit">Search</button>
</form>

<?php if ($searchTerm && $users): ?>
    <!-- If users are found with the search -->
    <h2>Search Results</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pseudo</th>
                <th>Account Number</th>
                <th>Secret Pseudo</th>
                <th>Wallet Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['pseudo']) ?></td>
                    <td><?= htmlspecialchars($user['account_number']) ?></td>
                    <td><?= htmlspecialchars($user['secret_pseudo']) ?></td>
                    <td><?= htmlspecialchars($user['wallet_balance']) ?></td>
                    <td>
                        <!-- Forms to modify each field -->
                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <label for="pseudo_<?= $user['id'] ?>">Pseudo:</label>
                            <input type="text" id="pseudo_<?= $user['id'] ?>" name="pseudo" value="<?= htmlspecialchars($user['pseudo']) ?>">
                            <label for="superadmin_password">Superadmin Password:</label>
                            <input type="password" name="superadmin_password" required autocomplete="off">
                            <button type="submit" class="button">Modify Pseudo</button>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <label for="password_<?= $user['id'] ?>">Password:</label>
                            <input type="password" id="password_<?= $user['id'] ?>" name="password">
                            <label for="superadmin_password">Superadmin Password:</label>
                            <input type="password" name="superadmin_password" required autocomplete="off">
                            <button type="submit" class="button">Modify Password</button>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <label for="secret_pseudo_<?= $user['id'] ?>">Secret Pseudo:</label>
                            <input type="text" id="secret_pseudo_<?= $user['id'] ?>" name="secret_pseudo" value="<?= htmlspecialchars($user['secret_pseudo']) ?>">
                            <label for="superadmin_password">Superadmin Password:</label>
                            <input type="password" name="superadmin_password" required autocomplete="off">
                            <button type="submit" class="button">Modify Secret Pseudo</button>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <label for="code_pin_<?= $user['id'] ?>">PIN Code:</label>
                            <input type="text" id="code_pin_<?= $user['id'] ?>" name="code_pin" value="<?= htmlspecialchars($user['code_pin']) ?>">
                            <label for="superadmin_password">Superadmin Password:</label>
                            <input type="password" name="superadmin_password" required autocomplete="off">
                            <button type="submit" class="button">Modify PIN Code</button>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <label for="wallet_balance_<?= $user['id'] ?>">Wallet Balance:</label>
                            <input type="number" id="wallet_balance_<?= $user['id'] ?>" name="wallet_balance" value="<?= htmlspecialchars($user['wallet_balance']) ?>">
                            <label for="superadmin_password">Superadmin Password:</label>
                            <input type="password" name="superadmin_password" required autocomplete="off">
                            <button type="submit" class="button">Modify Wallet Balance</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
