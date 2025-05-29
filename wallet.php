<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

// Security: is user logged in?
if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

$account_number = $_SESSION['account_number'];

// Check user role (user or superadmin)
$stmt = $db->prepare("SELECT role FROM users WHERE account_number = :account");
$stmt->execute(['account' => $account_number]);
$user_role = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user_role || ($user_role['role'] !== 'user' && $user_role['role'] !== 'superadmin')) {
    echo "Access denied.";
    exit;
}

// Retrieve wallet balance and PIN
$stmt = $db->prepare("SELECT wallet_balance, code_pin FROM users WHERE account_number = :account");
$stmt->execute(['account' => $account_number]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user['wallet_balance'];

// Load crypto prices (from cache or API)
$cache_file = 'cache/crypto_prices.json';
$cache_time = 300;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $prices = json_decode(file_get_contents($cache_file), true);
} else {
    $ch = curl_init("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,monero&vs_currencies=usd");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    $prices = $response ? json_decode($response, true) : [];
    file_put_contents($cache_file, json_encode($prices));
}

// Load reserves
$stmt = $db->prepare("SELECT * FROM crypto_reserves");
$stmt->execute();
$cryptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total wallet balances from regular users
$stmt = $db->prepare("SELECT SUM(wallet_balance) AS total_balance FROM users WHERE role = 'user'");
$stmt->execute();
$total_balance_global = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'];

$total_reserve_value = 0;
foreach ($cryptos as $crypto) {
    $crypto_name = strtolower($crypto['crypto_name']);
    $usd_price = $prices[$crypto_name]['usd'] ?? 0;
    $total_reserve_value += $crypto['reserve_amount'] * $usd_price;
}

$currency_value = ($total_balance_global > 0) ? $total_reserve_value / $total_balance_global : 0;
$balance_usd = $balance * $currency_value;

// Delete temporary wallet
if (isset($_POST['delete_wallet']) && isset($_POST['wallet_code'])) {
    $wallet_code = $_POST['wallet_code'];
    $del = $db->prepare("
        DELETE FROM transactions 
        WHERE wallet_code = :code 
          AND account_number = :account
    ");
    $del->execute([
        'code'    => $wallet_code,
        'account' => $account_number
    ]);
    header("Location: wallet.php");
    exit;
}

// Load active wallets
$stmt = $db->prepare("
    SELECT wallet_code, created_at 
      FROM transactions 
     WHERE account_number = :account 
  ORDER BY created_at DESC
");
$stmt->execute(['account' => $account_number]);
$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Wallet</title>
</head>
<body>
    <?php include('navigation.php'); ?>

    <h1>My Wallet</h1>
    <p>Balance: <?= htmlspecialchars($balance) ?> V (≈ <?= number_format($balance_usd, 2) ?> USD)</p>

    <form action="receive.php" method="get" style="display:inline;">
        <button type="submit">Receive</button>
    </form>
    <form action="transfer.php" method="get" style="display:inline;">
        <button type="submit">Transfer</button>
    </form>

    <h2>Active Wallets</h2>

    <?php if (empty($wallets)): ?>
        <p>No active wallets found.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Wallet Code</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
            <?php foreach ($wallets as $w): ?>
                <tr>
                    <td><?= htmlspecialchars($w['wallet_code']) ?></td>
                    <td><?= htmlspecialchars($w['created_at']) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete this wallet?');">
                            <input type="hidden" name="wallet_code" value="<?= htmlspecialchars($w['wallet_code']) ?>">
                            <button type="submit" name="delete_wallet">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>
