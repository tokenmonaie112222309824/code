<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Global admin authentication by password
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['global_admin_password'])) {
        $password = $_POST['global_admin_password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM admin_passwords LIMIT 1");
        $stmt->execute();
        $adminPassword = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminPassword && password_verify($password, $adminPassword['password_hash'])) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        echo '<form method="POST">';
        echo '<h2>Admin password required:</h2>';
        echo '<input type="password" name="global_admin_password" required>';
        echo '<button type="submit">Submit</button>';
        if (isset($error)) echo "<p style='color:red;'>$error</p>";
        echo '</form>';
        exit;
    }
}

// Processing admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = $_POST['order_id'];
    $action = $_POST['action'];

    if ($action === 'complete') {
        if (empty($_POST['admin_password'])) {
            $_SESSION['message'] = "Password required to complete the transaction.";
        } else {
            $stmt = $db->prepare("SELECT * FROM transaction_crypto WHERE id = :id AND status = 'pending' AND transaction_type = 'buy'");
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                $amount = $order['amount'];
                $userAcc = $order['account_number'];
                $cryptoType = strtolower($order['crypto_type']);  // Ensure this matches the JSON file
                $paidUSD = $order['price'];

                // Debug: Show the requested crypto type
                echo "Requested crypto: " . $cryptoType . "<br>";

                // Map crypto symbols to full names
                $cryptoMapping = [
                    'btc' => 'bitcoin',
                    'eth' => 'ethereum',
                    'xmr' => 'monero'
                ];

                // If the crypto type exists in the mapping, replace it with the full name
                if (array_key_exists($cryptoType, $cryptoMapping)) {
                    $cryptoType = $cryptoMapping[$cryptoType];
                }

                // Read the crypto prices file
                $pricesFile = __DIR__ . '/cache/crypto_prices.json';
                if (!file_exists($pricesFile)) {
                    $_SESSION['message'] = "Error: Price file not found.";
                    header("Location: purchase.php");
                    exit;
                }
                $prices = json_decode(file_get_contents($pricesFile), true);

                // Debug: Show the contents of the JSON file
                echo "<pre>";
                var_dump($prices);  // Displays the JSON file data
                echo "</pre>";

                // Check the validity of the JSON file
                if (!$prices) {
                    $_SESSION['message'] = "Error: The price file is invalid or empty.";
                    header("Location: purchase.php");
                    exit;
                }

                // Verify if the crypto exists in the JSON (convert to lowercase)
                if (!array_key_exists($cryptoType, $prices)) {
                    $_SESSION['message'] = "Error: Crypto not found in prices. Requested crypto: $cryptoType";
                    header("Location: purchase.php");
                    exit;
                }

                // Use the full crypto name to search for the price
                $cryptoPrice = $prices[$cryptoType]['usd'] ?? 0;

                if ($cryptoPrice == 0) {
                    $_SESSION['message'] = "Error: Crypto price not found.";
                    header("Location: purchase.php");
                    exit;
                }

                // Apply the fees
                $feeStmt = $db->prepare("SELECT fee_percent FROM fees WHERE fee_type = 'buy' LIMIT 1");
                $feeStmt->execute();
                $feeRow = $feeStmt->fetch(PDO::FETCH_ASSOC);
                $feePercent = $feeRow ? floatval($feeRow['fee_percent']) : 5;
                $usdAfterFee = $paidUSD * (1 - $feePercent / 100);

                // Calculate how much crypto the user sent
                $cryptoReceived = $usdAfterFee / $cryptoPrice;

                // Check superadmin funds
                $stmt = $db->prepare("SELECT * FROM users WHERE role = 'superadmin' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && $admin['wallet_balance'] >= $amount) {
                    // Deduct admin balance
                    $db->prepare("UPDATE users SET wallet_balance = wallet_balance - :amount WHERE id = :admin_id")
                        ->execute(['amount' => $amount, 'admin_id' => $admin['id']]);

                    // Credit user balance
                    $db->prepare("UPDATE users SET wallet_balance = wallet_balance + :amount WHERE account_number = :acc")
                        ->execute(['amount' => $amount, 'acc' => $userAcc]);

                    // Add the `price` to the reserve of the correct crypto
                    $cryptoReceived = $order['price'];  // The value in the `price` field is what needs to be added to the reserve
                    $db->prepare("UPDATE crypto_reserves SET reserve_amount = reserve_amount + :received WHERE crypto_name = :crypto")
                        ->execute([
                            'received' => $cryptoReceived,
                            'crypto' => $cryptoType
                        ]);

                    // Update transaction status
                    $db->prepare("UPDATE transaction_crypto SET status = 'completed' WHERE id = :id")
                        ->execute(['id' => $orderId]);

                    $_SESSION['message'] = "Payment completed successfully.";
                } else {
                    $_SESSION['message'] = "Insufficient superadmin funds.";
                }
            }
        }
    } elseif ($action === 'fail') {
        $db->prepare("UPDATE transaction_crypto SET status = 'failed' WHERE id = :id")
            ->execute(['id' => $orderId]);
        $_SESSION['message'] = "Order marked as failed.";
    }
}

// Retrieve orders
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$searchQuery = "";
$params = [];

if ($search !== '') {
    $searchQuery = " AND (crypto_address = :search OR account_number = :search OR EXISTS (SELECT 1 FROM users WHERE users.account_number = transaction_crypto.account_number AND (pseudo = :search OR secret_pseudo = :search)))";
    $params['search'] = $search;
}

$stmt = $db->prepare("SELECT * FROM transaction_crypto WHERE status = :status AND transaction_type = 'buy' $searchQuery ORDER BY timestamp DESC");
$params['status'] = $status;
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders <?= ucfirst($status) ?></title>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Address copied: " + text);
            });
        }
    </script>
</head>
<body>
<h1>Orders <?= ucfirst($status) ?></h1>
<?php include('navigation.php'); ?>
<p style="color:green;">
    <?= $_SESSION['message'] ?? '' ?>
    <?php unset($_SESSION['message']); ?>
</p>

<nav>
    <a href="?status=pending">Pending</a> |
    <a href="?status=completed">Completed</a> |
    <a href="?status=failed">Failed</a>
</nav>

<form method="GET">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="text" name="search" placeholder="Address, username, secret, account number" value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
</form>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Crypto</th>
        <th>Paid CRYPTO</th>
        <th>Amount</th>
        <th>Address</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= $order['id'] ?></td>
            <td><?= $order['crypto_type'] ?></td>
            <td><?= $order['price'] ?></td>
            <td><?= $order['amount'] ?></td>
            <td><?= $order['crypto_address'] ?></td>
            <td>
                <?php if ($status === 'pending'): ?>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="password" name="admin_password" placeholder="Password" required>
                        <button type="submit" name="action" value="complete">Pay</button>
                    </form>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" name="action" value="fail">Fail</button>
                    </form>
                <?php endif; ?>
                <button onclick="copyToClipboard('<?= $order['crypto_address'] ?>')">Copy Address</button>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
