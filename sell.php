<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

// Récupération des prix depuis le cache
$cache_file = 'cache/crypto_prices.json';
$cache_time = 300;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $prices = json_decode(file_get_contents($cache_file), true);
} else {
    $ch = curl_init("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,monero&vs_currencies=usd");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === FALSE) {
        die('Error retrieving data from the API');
    }

    $prices = json_decode($response, true);
    file_put_contents($cache_file, json_encode($prices));
}

$account_number = $_SESSION['account_number'];
$stmt = $db->prepare("SELECT role FROM users WHERE account_number = :account_number");
$stmt->execute(['account_number' => $account_number]);
$user_role_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_role_data || ($user_role_data['role'] !== 'user' && $user_role_data['role'] !== 'superadmin')) {
    echo "Access denied.";
    exit;
}

$stmt = $db->prepare("SELECT wallet_balance, code_pin FROM users WHERE account_number = :account_number");
$stmt->execute(['account_number' => $account_number]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$wallet_balance = $user['wallet_balance'];
$code_pin_bdd = $user['code_pin'];

$stmt = $db->prepare("SELECT * FROM crypto_reserves");
$stmt->execute();
$cryptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT SUM(wallet_balance) AS total_balance FROM users WHERE role = 'user'");
$stmt->execute();
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'];

$total_reserve_value = 0;
foreach ($cryptos as $crypto) {
    $name = strtolower($crypto['crypto_name']);
    if (isset($prices[$name]['usd'])) {
        $total_reserve_value += $crypto['reserve_amount'] * $prices[$name]['usd'];
    }
}

$currency_value = $total_balance > 0 ? $total_reserve_value / $total_balance : 0;
$minimum_amount_vexium = $currency_value > 0 ? 4 / $currency_value : 0;

$error_message = '';
$success_message = '';
if (isset($_POST['sell'])) {
    $selected_crypto = $_POST['crypto'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $crypto_address = $_POST['crypto_address'] ?? '';
    $code_pin = $_POST['code_pin'] ?? '';

    $crypto_map = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'XMR' => 'monero'
    ];

    $crypto_name = $crypto_map[$selected_crypto] ?? '';

    if ($amount < $minimum_amount_vexium) {
        $error_message = "The minimum amount to sell is {$minimum_amount_vexium} VEXIUM (equivalent to 4 USD).";
    } elseif ($amount > $wallet_balance) {
        $error_message = "You do not have enough balance in your wallet.";
    } elseif ($code_pin !== $code_pin_bdd) {
        $error_message = "Incorrect PIN code.";
    } elseif ($crypto_name === '' || !isset($prices[$crypto_name]['usd'])) {
        $error_message = "Price not available for the selected crypto.";
    } else {
        $usd_value = $amount * $currency_value;
        $price_in_crypto = $usd_value / $prices[$crypto_name]['usd'];

        // Vérifier la réserve et si le retrait est activé
        $stmt = $db->prepare("SELECT reserve_amount, withdraw_enabled FROM crypto_reserves WHERE crypto_name = :crypto_name");
        $stmt->execute(['crypto_name' => $crypto_name]);
        $reserve_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reserve_data || $price_in_crypto > $reserve_data['reserve_amount']) {
            $error_message = "Insufficient reserve for the selected cryptocurrency.";
        } elseif ((int)$reserve_data['withdraw_enabled'] === 0) {
            $error_message = "This cryptocurrency is unavailable at the moment.";
        } else {
            // Insérer la transaction
            $stmt = $db->prepare("INSERT INTO transaction_crypto 
                (account_number, crypto_type, crypto_address, amount, price, status, transaction_type)
                VALUES (:account_number, :crypto_type, :crypto_address, :amount, :price, 'pending', 'sell')");
            $stmt->execute([
                'account_number' => $account_number,
                'crypto_type' => $selected_crypto,
                'crypto_address' => $crypto_address,
                'amount' => $amount,
                'price' => $price_in_crypto
            ]);

            // Déduire du wallet
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - :amount WHERE account_number = :account_number");
            $stmt->execute([
                'amount' => $amount,
                'account_number' => $account_number
            ]);

            // Déduire de la réserve crypto
            $stmt = $db->prepare("UPDATE crypto_reserves SET reserve_amount = reserve_amount - :amount_sent WHERE crypto_name = :crypto_name");
            $stmt->execute([
                'amount_sent' => $price_in_crypto,
                'crypto_name' => $crypto_name
            ]);

            header("Location: sell.php?success=true");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/sell.css">
</head>
<body>
    <h1>💰 Sell Currency</h1>
    <?php include('navigation.php'); ?>

    <?php if (isset($_GET['success'])): ?>
        <p style="color:green;">Your sale has been successfully recorded!</p>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <p style="color:red;"><?= $error_message ?></p>
    <?php endif; ?>

    <form action="sell.php" method="POST">
        <label for="wallet_balance">Your current balance:</label>
        <input type="text" id="wallet_balance" value="<?= $wallet_balance ?>" disabled><br><br>

        <label for="amount">Amount to sell (minimum <?= number_format($minimum_amount_vexium, 4) ?> VEXIUM):</label>
        <input type="number" name="amount" id="amount" min="<?= $minimum_amount_vexium ?>" step="any" value="<?= $minimum_amount_vexium ?>" required><br><br>
        <button type="button" onclick="document.getElementById('amount').value = <?= $wallet_balance; ?>;">Max</button><br><br>

        <label for="crypto">Choose a cryptocurrency:</label>
        <select name="crypto" id="crypto" required>
            <option value="BTC">Bitcoin (BTC)</option>
            <option value="ETH">Ethereum (ETH)</option>
            <option value="XMR">Monero (XMR)</option>
        </select><br><br>

        <label for="crypto_address">Receiving crypto address:</label>
        <input type="text" name="crypto_address" id="crypto_address" required><br><br>

        <label for="code_pin">PIN Code:</label>
        <input type="password" name="code_pin" id="code_pin" required maxlength="6"><br><br>

        <button type="submit" name="sell">Sell</button>
    </form>
</body>
</html>
