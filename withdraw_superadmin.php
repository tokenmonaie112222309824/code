<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check superadmin password upon arrival on the page
if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify the superadmin password
        $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
        $stmt->execute();
        $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify hashed password
        if (password_verify($_POST['password'], $passwordRow['password_hash'])) {
            $_SESSION['is_authenticated'] = true;
        } else {
            echo "Incorrect password.";
            exit;
        }
    }

    // If not authenticated, display password form
    echo '<form method="POST">
            <label for="password">Superadmin password:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Log in</button>
          </form>';
    exit;
}

// Retrieve filtered status (if set)
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Retrieve "sell" transactions with status filter if needed
$transactions = [];
$query = "SELECT * FROM transaction_crypto WHERE transaction_type = 'sell'";

// Add status filter if the user has selected a specific status
if ($status_filter) {
    $query .= " AND status = :status";
}

$stmt = $db->prepare($query);

// Bind the status parameter if a filter is applied
if ($status_filter) {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If the status is modified and the password is requested
if (isset($_POST['transaction_id']) && isset($_POST['new_status'])) {
    // Check superadmin password before changing status
    if (!isset($_POST['superadmin_password']) || $_POST['superadmin_password'] == '') {
        echo "Please enter the superadmin password to change the status.";
        exit;
    }

    // Verify superadmin password
    $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
    $stmt->execute();
    $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify hashed password
    if (!password_verify($_POST['superadmin_password'], $passwordRow['password_hash'])) {
        echo "Incorrect password.";
        exit;
    }

    $transaction_id = $_POST['transaction_id'];
    $new_status = $_POST['new_status'];

    // Update the transaction status
    $updateStmt = $db->prepare("UPDATE transaction_crypto SET status = :status WHERE id = :id");
    $updateStmt->bindParam(':status', $new_status);
    $updateStmt->bindParam(':id', $transaction_id);
    $updateStmt->execute();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdrawal Management - Superadmin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .transaction-table { width: 100%; border-collapse: collapse; }
        .transaction-table th, .transaction-table td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .transaction-table th { background-color: #f2f2f2; }
        .button { margin: 5px; padding: 5px 10px; cursor: pointer; }
        .button-group { margin-bottom: 20px; }
    </style>
</head>
<body>

<h1>Withdrawal Management (Sell Transactions)</h1>
<?php include('navigation.php'); ?>
<!-- Transaction filtering -->
<div class="button-group">
    <a href="?status=pending" class="button">Pending</a>
    <a href="?status=completed" class="button">Completed</a>
    <a href="?status=failed" class="button">Failed</a>
</div>

<!-- Display transactions -->
<h2>"Sell" Transactions</h2>
<table class="transaction-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Account</th>
            <th>Crypto</th>
            <th>Address</th>
            <th>Amount</th>
            <th>Price</th>
            <th>Status</th>
            <th>Date</th>
            <th>Change Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $transaction): ?>
            <tr>
                <td><?= htmlspecialchars($transaction['id']) ?></td>
                <td><?= htmlspecialchars($transaction['account_number']) ?></td>
                <td><?= htmlspecialchars($transaction['crypto_type']) ?></td>
                <td><?= htmlspecialchars($transaction['crypto_address']) ?></td>
                <td><?= htmlspecialchars($transaction['amount']) ?></td>
                <td><?= htmlspecialchars($transaction['price']) ?></td>
                <td><?= htmlspecialchars($transaction['status']) ?></td>
                <td><?= htmlspecialchars($transaction['timestamp']) ?></td>
                <td>
                    <form method="POST" action="">
                        <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction['id']) ?>">
                        <input type="password" name="superadmin_password" placeholder="Superadmin password" required>
                        <select name="new_status">
                            <option value="pending" <?= $transaction['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= $transaction['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="failed" <?= $transaction['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                        <button type="submit" class="button">Change</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
