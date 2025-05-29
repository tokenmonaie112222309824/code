<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

// Check if the prices have been retrieved recently (within 5 minutes)
$cache_file = 'cache/crypto_prices.json';
$cache_time = 300;  // 300 seconds = 5 minutes

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    // Use the cache if the data is recent
    $prices = json_decode(file_get_contents($cache_file), true);
} else {
    // If the cache is too old, fetch prices again
    $api_url = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,monero&vs_currencies=usd";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === FALSE) {
        die('Error retrieving data from the API');
    }

    // Decode the received JSON data
    $prices = json_decode($response, true);

    // Save the data in the cache for 5 minutes
    file_put_contents($cache_file, json_encode($prices));
}

// Calculate the current value of the currency
$stmt = $db->prepare("SELECT * FROM crypto_reserves");
$stmt->execute();
$cryptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve the sum of user balances (excluding administrators)
$stmt = $db->prepare("SELECT SUM(wallet_balance) AS total_balance FROM users WHERE role = 'user'");
$stmt->execute();
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'];

// Calculate the value of the currency
$total_reserve_value = 0;
foreach ($cryptos as $crypto) {
    $crypto_name = strtolower($crypto['crypto_name']); // Convert crypto name to lowercase
    if (isset($prices[$crypto_name]['usd'])) {
        $current_price = $prices[$crypto_name]['usd']; // Current price of the crypto
    } else {
        $current_price = 0; // If the price is not found, set it to 0
    }

    $total_reserve_value += $crypto['reserve_amount'] * $current_price;
}

// Calculate the value of the "currency"
if ($total_balance > 0) {
    $currency_value = $total_reserve_value / $total_balance;
} else {
    $currency_value = 0;
}

// Calculate the total currency in circulation
$total_currency_in_circulation = number_format($total_balance, 2, '.', '');
?>

<!DOCTYPE html>
<html>
<head>
<?php include('header.php'); ?>
<link rel="stylesheet" href="../assets/css/valeur.css">
</head>
<body>

<div style="text-align:center; margin-top: 50px;">
    <h1>💸 VEXIUM</h1>
    <?php include('navigation.php'); ?>
    <h2>1 Vexium<img src="../assets/logo.PNG" alt="Logo" width="200">
    ≈ <?= number_format($currency_value, 8) ?> USD</h2>
</div>


    <hr style="margin: 40px 0;">

    <div style="padding: 0 20px;">
        <h3>🔒 Current Reserves</h3>
        <table border="1" cellpadding="8" style="width: 100%; border-collapse: collapse; text-align: center;">
            <tr>
                <th>Crypto</th>
                <th>Reserve Amount</th>
                <th>Current Price</th>
                <th>Total Value</th>
            </tr>
            <?php foreach ($cryptos as $crypto): ?>
                <tr>
                    <td><?= $crypto['crypto_name'] ?></td>
                    <td><?= number_format($crypto['reserve_amount'], 8) ?></td>
                    <td>
                        <?php
                        $crypto_name = strtolower($crypto['crypto_name']);
                        if (isset($prices[$crypto_name]['usd'])) {
                            echo number_format($prices[$crypto_name]['usd'], 2) . ' USD';
                        } else {
                            echo 'Not Available';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (isset($prices[$crypto_name]['usd'])) {
                            echo number_format($crypto['reserve_amount'] * $prices[$crypto_name]['usd'], 2) . ' USD';
                        } else {
                            echo '0.00 USD';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <div class="currency-info">
    <h3>💰 Total Vexium in Circulation</h3>
    <p>There are currently <strong><?= $total_currency_in_circulation ?></strong>  Vexium in circulation.</p>
</div>


</body>

</html>
