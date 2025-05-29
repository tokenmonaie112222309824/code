<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

if (!isset($_SESSION['account_number'])) {
    echo "⛔ Access denied. Please log in.";
    exit;
}

$account_number = $_SESSION['account_number'];

// Vérifie rôle
$stmt = $db->prepare("SELECT role FROM users WHERE account_number = :acc");
$stmt->execute(['acc' => $account_number]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'user' && $user['role'] !== 'superadmin')) {
    echo "⛔ Access restricted to users.";
    exit;
}

// Chargement des prix depuis le cache
$cache_file = '../cache/crypto_prices.json';
$prices = json_decode(file_get_contents($cache_file), true);

// Réserves crypto
$stmt = $db->prepare("SELECT * FROM crypto_reserves");
$stmt->execute();
$reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Somme wallets utilisateurs
$stmt = $db->prepare("SELECT SUM(wallet_balance) as total FROM users WHERE role = 'user'");
$stmt->execute();
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Valeur monnaie interne
$total_reserve_value = 0;
foreach ($reserves as $crypto) {
    $name = strtolower($crypto['crypto_name']);
    $amount = $crypto['reserve_amount'];
    $price = $prices[$name]['usd'] ?? 0;
    $total_reserve_value += $amount * $price;
}
$monaie_value = ($total_balance > 0) ? ($total_reserve_value / $total_balance) : 0;

// Frais
$stmt = $db->prepare("SELECT fee_percent FROM fees WHERE fee_type = 'buy' LIMIT 1");
$stmt->execute();
$fee_percent = floatval($stmt->fetchColumn() ?? 5.0);
$fee_multiplier = 1 + ($fee_percent / 100);

// Récupère les frais de sécurité
$stmt = $db->prepare("SELECT fee_percent FROM security_fees ORDER BY id DESC LIMIT 1");
$stmt->execute();
$security_fee_percent = floatval($stmt->fetchColumn() ?? 0.1);  // Default à 0.1% si aucune entrée dans la table
$security_fee_multiplier = 1 + ($security_fee_percent / 100);

$message = "";

if (isset($_POST['reset_order'])) {
    unset($_SESSION['last_order']);
    header("Location: buy.php");
    exit;
}

if (isset($_SESSION['last_order'])) {
    $message = $_SESSION['last_order'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $crypto_type = strtoupper(trim($_POST['crypto_type'] ?? ''));
    $usd_amount = floatval($_POST['usd_amount']);

    $crypto_key_map = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'XMR' => 'monero'
    ];

    $crypto_key = $crypto_key_map[$crypto_type] ?? null;
    $crypto_price = $prices[$crypto_key]['usd'] ?? null;

    if ($usd_amount < 4) {
        $message = "The minimum amount is 4 USD.";
    } elseif ($crypto_price === null) {
        $message = "Invalid cryptocurrency.";
    } else {
        // Vérifie si achat avec même crypto dans les dernières 24h
        $stmt = $db->prepare("SELECT 1 FROM transaction_crypto 
            WHERE account_number = :acc 
            AND crypto_type = :crypto 
            AND transaction_type = 'buy'
            AND timestamp >= NOW() - INTERVAL 24 HOUR");
        $stmt->execute([
            'acc' => $account_number,
            'crypto' => $crypto_type
        ]);
        $recent = $stmt->fetch();

        if ($recent) {
            $message = "You have already made a purchase with this cryptocurrency in the last 24 hours.";
        } else {
            // Calculs
            $usd_with_fee = $usd_amount * $fee_multiplier;
            $usd_with_security_fee = $usd_with_fee * $security_fee_multiplier;  // Applique les frais de sécurité
            $crypto_amount = $usd_with_security_fee / $crypto_price;
            $monaie_amount = $usd_amount / $monaie_value;

            // Adresse crypto
            $stmt = $db->prepare("SELECT * FROM crypto_addresses WHERE crypto_type = :type LIMIT 1");
            $stmt->execute(['type' => $crypto_type]);
            $address = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($address) {
                $crypto_address = $address['address'];

                // Supprime l'adresse utilisée
                $del = $db->prepare("DELETE FROM crypto_addresses WHERE id = :id");
                $del->execute(['id' => $address['id']]);


                // Enregistrement de la transaction
                $stmt = $db->prepare("INSERT INTO transaction_crypto 
                    (account_number, crypto_type, crypto_address, amount, price, status, timestamp, transaction_type)
                    VALUES (:account_number, :crypto_type, :crypto_address, :amount, :price, 'pending', NOW(), 'buy')");
                $stmt->execute([
                    'account_number' => $account_number,
                    'crypto_type' => $crypto_type,
                    'crypto_address' => $crypto_address,
                    'amount' => $monaie_amount, // VEXIUM à recevoir
                    'price' => $crypto_amount   // Crypto à envoyer
                ]);


                $message = [
                    'usd_amount' => number_format($usd_amount, 2),
                    'crypto_type' => $crypto_type,
                    'crypto_address' => $crypto_address,
                    'crypto_amount' => number_format($crypto_amount, 8),
                    'monaie_amount' => number_format($monaie_amount, 8)
                ];
                $_SESSION['last_order'] = $message;
            } else {
                $message = "No address available for this cryptocurrency type.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/buy.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Buy Internal Currency</h1>
        <?php include('navigation.php'); ?>

        <?php if (!empty($message) && is_array($message)): ?>
            <h2>🎉 Your order is in progress</h2>
            <p>💰 Amount to pay (with fees): <strong><?= $message['usd_amount'] ?> USD</strong></p>
            <p>≈ <strong><?= $message['crypto_amount'] ?> <?= $message['crypto_type'] ?></strong></p>
            <p>🎯 VEXIUM you will receive: <strong><?= $message['monaie_amount'] ?></strong></p>
            <p>🏦 Payment address: <strong id="crypto_address"><?= $message['crypto_address'] ?></strong></p>

            <canvas id="qrcode" style="margin-top: 15px;"></canvas>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const address = document.getElementById("crypto_address").textContent.trim();
                    new QRious({
                        element: document.getElementById("qrcode"),
                        value: address,
                        size: 200
                    });
                });
            </script>

            <form method="post">
                <input type="hidden" name="reset_order" value="1">
                <button type="submit">Make another purchase</button>
            </form>

        <?php elseif (!empty($message)): ?>
            <p class="error"><?= htmlspecialchars($message) ?></p>

        <?php else: ?>
            <form method="POST">
                <label>Choose a crypto:</label><br>
                <select name="crypto_type" required>
                    <option value="BTC">Bitcoin (BTC)</option>
                    <option value="ETH">Ethereum (ETH)</option>
                    <option value="XMR">Monero (XMR)</option>
                </select><br><br>

                <label>How many USD would you like to spend?</label><br>
                <input type="number" name="usd_amount" id="usd_amount" step="0.01" min="0.01" required oninput="updateMonaie(); validateUSD();"><br>
                <p>💰 Estimated currency received: <span id="estimated_monaie">0.0000</span> vexium</p>
                <p id="usd_error" class="error" style="display:none;">The minimum amount is 4 USD.</p>
                <button type="submit">Buy</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const monaieValue = <?= $monaie_value ?>;
        const feeMultiplier = <?= $fee_multiplier ?>;

        function updateMonaie() {
            const usdAmount = parseFloat(document.getElementById('usd_amount').value) || 0;
            const usdWithoutFee = usdAmount / feeMultiplier;
            const monaieAmount = usdWithoutFee / monaieValue;
            document.getElementById('estimated_monaie').textContent = monaieAmount.toFixed(4);
        }

        function validateUSD() {
            const usdAmount = parseFloat(document.getElementById('usd_amount').value);
            const errorElem = document.getElementById('usd_error');
            if (usdAmount < 4) {
                errorElem.style.display = 'block';
            } else {
                errorElem.style.display = 'none';
            }
        }
    </script>
</body>
</html>
