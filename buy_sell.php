<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    echo "You must be logged in to access this page.";
    exit;
}

$account_number = $_SESSION['account_number'];

// ✅ Mettre à jour les transactions "pending" de plus de 24h en "failed",
// sauf celles de type "sell"
$update_stmt = $db->prepare("
    UPDATE transaction_crypto
    SET status = 'failed'
    WHERE status = 'pending' 
    AND account_number = :account_number 
    AND timestamp < NOW() - INTERVAL 24 HOUR
    AND transaction_type != 'sell'  -- Exclure les transactions de type 'sell'
");
$update_stmt->execute(['account_number' => $account_number]);

// 🔄 Ensuite on récupère les transactions à jour
$stmt = $db->prepare("SELECT * FROM transaction_crypto WHERE account_number = :account_number AND status = 'pending'");
$stmt->execute(['account_number' => $account_number]);
$pending_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve transaction history
$stmt = $db->prepare("SELECT * FROM transaction_crypto WHERE account_number = :account_number AND status IN ('completed', 'failed') ORDER BY timestamp DESC");
$stmt->execute(['account_number' => $account_number]);
$transaction_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve the fees from the database
$fee_stmt = $db->prepare("SELECT fee_type, fee_percent FROM fees");
$fee_stmt->execute();
$fees = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/buy_sell.css">
</head>
<body>
    <h1>💰 Transaction History and Buy/Sell</h1>
    <?php include('navigation.php'); ?>

    <div class="centered-content" style="color: white; font-family: Arial, sans-serif;">
    <p style="font-size: 18px; color: #9b59b6; font-weight: bold;">
        0% transaction fee, 0% withdrawal fee
    </p>

    <p style="margin-top: 10px; color: #ccc;">Buy fees may apply depending on the currency:</p>

    <table style="border-collapse: collapse; width: 300px; color: white; border: 1px solid #9b59b6;">
        <tr style="background-color: #444;">
            <th style="border: 1px solid #9b59b6; padding: 8px;">Fee (%)</th>
        </tr>
        <?php foreach ($fees as $fee): ?>
            <tr style="background-color: #555;">
                <td style="border: 1px solid #9b59b6; padding: 8px;"><?php echo htmlspecialchars($fee['fee_percent']); ?>%</td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

    <!-- Buttons -->
    <div class="buttons-container">
        <a href="buy.php"><button class="buy-sell-btn">Buy Cryptocurrency</button></a>
        <a href="sell.php"><button class="buy-sell-btn">Sell Cryptocurrency</button></a>
    </div>

    <!-- Pending Transactions -->
    <h2>Pending Transactions</h2>
    <?php if (empty($pending_transactions)): ?>
        <p>No pending transactions.</p>
    <?php else: ?>
        <table border="1" class="transaction-table">
            <thead>
                <tr>
                    <th>Crypto Type</th>
                    <th>Price</th>
                    <th>Crypto Address</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Transaction Type</th>
                    <th>Time Remaining</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($pending_transactions as $transaction): ?>
        <?php 
        // Vérifier si la transaction est de type "sell"
        $is_sell = ($transaction['transaction_type'] === 'sell');
        
        // Si ce n'est pas une transaction de type "sell", calculer le temps restant
        if (!$is_sell) {
            // Calcul du temps restant pour les transactions autres que "sell"
            $timestamp = strtotime($transaction['timestamp']);
            $time_diff = time() - $timestamp;
            $time_left = 86400 - $time_diff; // 86400 secondes dans 24 heures
        }
        ?>
        <tr>
            <td><?= htmlspecialchars($transaction['crypto_type']) ?></td>
            <td><?= htmlspecialchars($transaction['price']) ?> <?= htmlspecialchars($transaction['crypto_type']) ?></td>
            <td><?= htmlspecialchars($transaction['crypto_address']) ?></td>
            <td><?= htmlspecialchars($transaction['amount']) ?>V</td>
            <td><?= ucfirst(htmlspecialchars($transaction['status'])) ?></td>
            <td><?= ucfirst(htmlspecialchars($transaction['transaction_type'])) ?></td>
            <td>
                <?php if ($is_sell): ?>
                    <span>No expiration</span>
                <?php elseif ($time_left > 0): ?>
                    <span id="countdown_<?= $transaction['id'] ?>" data-time="<?= $time_left ?>"></span>
                <?php else: ?>
                    <span>Expired</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

        </table>
    <?php endif; ?>

    <hr>

    <script>
        // Countdown Function
        function startCountdown() {
            const countdowns = document.querySelectorAll('[id^="countdown_"]');

            countdowns.forEach(function(countdown) {
                const timeLeft = parseInt(countdown.dataset.time);
                let remainingTime = timeLeft;

                const interval = setInterval(function() {
                    const hours = Math.floor(remainingTime / 3600);
                    const minutes = Math.floor((remainingTime % 3600) / 60);
                    const seconds = remainingTime % 60;

                    countdown.textContent = `${hours}h ${minutes}m ${seconds}s`;

                    remainingTime--;

                    if (remainingTime < 0) {
                        clearInterval(interval);
                        countdown.textContent = "Expired";
                    }
                }, 1000);
            });
        }

        // Start countdowns on page load
        window.onload = startCountdown;
    </script>

</body>
</html>
