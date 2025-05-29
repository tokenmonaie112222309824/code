<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Superadmin password verification when accessing the page
if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify superadmin password
        $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
        $stmt->execute();
        $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check hashed password
        if (password_verify($_POST['password'], $passwordRow['password_hash'])) {
            $_SESSION['is_authenticated'] = true;
        } else {
            echo "Incorrect password.";
            exit;
        }
    }

    // If not authenticated, display the password form
    echo '<form method="POST">
            <label for="password">Superadmin password:</label>
            <input type="password" id="password" name="password" required autocomplete="off">
            <button type="submit">Log in</button>
          </form>';
    exit;
}

// Filter users by username if a search term is provided
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Retrieve admins (users with the role "admin")
$admins = [];
$query = "SELECT * FROM users WHERE role = 'admin'";

// Add a search filter by username if a search term is specified
if ($search) {
    $query .= " AND pseudo LIKE :search";
}

$stmt = $db->prepare($query);

// Bind the search parameter if defined
if ($search) {
    $stmt->bindValue(':search', '%' . $search . '%');
}

$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If a modification is performed and the superadmin password is provided
if (isset($_POST['user_id'])) {
    if (!isset($_POST['superadmin_password']) || $_POST['superadmin_password'] == '') {
        echo "Please enter the superadmin password to make this modification.";
        exit;
    }

    // Verify superadmin password
    $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
    $stmt->execute();
    $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check hashed password
    if (!password_verify($_POST['superadmin_password'], $passwordRow['password_hash'])) {
        echo "Incorrect password.";
        exit;
    }

    // Get data to modify
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

<h1>Account Management (Admins)</h1>
<?php include('navigation.php'); ?>
<!-- User search -->
<form method="GET" action="">
    <input type="text" name="search" placeholder="Search for an account" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
    <button type="submit">Search</button>
</form>

<!-- Admin list -->
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Wallet Balance</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($admins as $admin): ?>
            <tr>
                <td><?= htmlspecialchars($admin['id']) ?></td>
                <td><?= htmlspecialchars($admin['pseudo']) ?></td>
                <td><?= htmlspecialchars($admin['wallet_balance']) ?></td>
                <td>
                    <!-- Forms for modification for each field -->
                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                        <label for="pseudo_<?= $admin['id'] ?>">Username:</label>
                        <input type="text" id="pseudo_<?= $admin['id'] ?>" name="pseudo" value="<?= htmlspecialchars($admin['pseudo']) ?>">
                        <label for="superadmin_password">Superadmin password:</label>
                        <input type="password" name="superadmin_password" required autocomplete="off">
                        <button type="submit" class="button">Modify Username</button>
                    </form>

                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                        <label for="password_<?= $admin['id'] ?>">Password:</label>
                        <input type="password" id="password_<?= $admin['id'] ?>" name="password">
                        <label for="superadmin_password">Superadmin password:</label>
                        <input type="password" name="superadmin_password" required autocomplete="off">
                        <button type="submit" class="button">Modify Password</button>
                    </form>

                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                        <label for="secret_pseudo_<?= $admin['id'] ?>">Secret Username:</label>
                        <input type="text" id="secret_pseudo_<?= $admin['id'] ?>" name="secret_pseudo" value="<?= htmlspecialchars($admin['secret_pseudo']) ?>">
                        <label for="superadmin_password">Superadmin password:</label>
                        <input type="password" name="superadmin_password" required autocomplete="off">
                        <button type="submit" class="button">Modify Secret Username</button>
                    </form>

                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                        <label for="code_pin_<?= $admin['id'] ?>">PIN Code:</label>
                        <input type="text" id="code_pin_<?= $admin['id'] ?>" name="code_pin" value="<?= htmlspecialchars($admin['code_pin']) ?>">
                        <label for="superadmin_password">Superadmin password:</label>
                        <input type="password" name="superadmin_password" required autocomplete="off">
                        <button type="submit" class="button">Modify PIN Code</button>
                    </form>

                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                        <label for="wallet_balance_<?= $admin['id'] ?>">Wallet Balance:</label>
                        <input type="number" id="wallet_balance_<?= $admin['id'] ?>" name="wallet_balance" value="<?= htmlspecialchars($admin['wallet_balance']) ?>" step="0.01">
                        <label for="superadmin_password">Superadmin password:</label>
                        <input type="password" name="superadmin_password" required autocomplete="off">
                        <button type="submit" class="button">Modify Wallet Balance</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
