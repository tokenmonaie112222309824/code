<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if user is superadmin
if (!isset($_SESSION['account_number']) || $_SESSION['role'] !== 'superadmin') {
    echo "Access Denied.";
    exit;
}

// Superadmin password verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the submitted password
    $submitted_password = $_POST['password'] ?? '';
    
    // Look up the password in the database
    $stmt = $db->query("SELECT * FROM superadmin_passwords ORDER BY id DESC LIMIT 1");
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($superadmin && password_verify($submitted_password, $superadmin['password_hash'])) {
        // If the password is correct, continue to the page
        $_SESSION['superadmin_authenticated'] = true;
    } else {
        // If the password is incorrect
        echo "Incorrect password. Access denied.";
        exit;
    }
}

// If user is authenticated, show the page content
if (isset($_SESSION['superadmin_authenticated']) && $_SESSION['superadmin_authenticated']) {
    // Which tab is active?
    $type = $_GET['type'] ?? 'transaction';
    $statusFilter = $_GET['status'] ?? ''; // For sub-filters

    // Search
    $search = '';
    if (isset($_POST['search'])) {
        $search = trim($_POST['search']);
    }

    // Fetch transactions from the `transactions` table
    $transactions = [];
    if ($type === 'transaction') {
        $query = "SELECT * FROM transactions";
        $params = [];

        // Filter by confirmation status
        if (!empty($statusFilter)) {
            $query .= " WHERE is_confirmed = :status";
            $params['status'] = $statusFilter === 'confirmed' ? 1 : 0;
        }

        // Search by ID, account number, etc.
        if (!empty($search)) {
            $query .= !empty($params) ? " AND" : " WHERE";
            $query .= " (id LIKE :search OR sender_id LIKE :search OR receiver_id LIKE :search OR account_number LIKE :search)";
            $params['search'] = "%$search%";
        }

        // Execute the query to retrieve transactions
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // If the user is not authenticated, display the password form
    echo '<form method="POST" action="">
            <label for="password">Superadmin Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Submit</button>
          </form>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions - Superadmin</title>

</head>
<body>

<h1>Transaction Management</h1>
<?php include('navigation.php'); ?>
<!-- Main Filters -->
<div class="filters">
    <a href="?type=transaction" class="<?= $type === 'transaction' ? 'active' : '' ?>">Classic Transactions</a>
</div>

<!-- Search Form -->
<form method="POST">
    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
</form>

<div class="transaction-block transaction">
    <?php if ($type === 'transaction'): ?>
        <h2>Classic Transactions</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sender ID</th>
                    <th>Receiver ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Account Number</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['id']) ?></td>
                        <td><?= htmlspecialchars($t['sender_id']) ?></td>
                        <td><?= htmlspecialchars($t['receiver_id']) ?></td>
                        <td><?= htmlspecialchars($t['amount']) ?></td>
                        <td><?= $t['is_confirmed'] ? 'Confirmed' : 'Not Confirmed' ?></td>
                        <td><?= htmlspecialchars($t['created_at']) ?></td>
                        <td><?= htmlspecialchars($t['account_number']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
