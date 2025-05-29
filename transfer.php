<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

$account_number = $_SESSION['account_number'];

// Get balance and PIN code
$stmt = $db->prepare("SELECT wallet_balance, code_pin FROM users WHERE account_number = :account");
$stmt->execute(['account' => $account_number]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user['wallet_balance'];
$code_pin = $user['code_pin'];

// Load cached prices or fetch from API
$cache_file = 'cache/crypto_prices.json';
$cache_time = 300;
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $prices = json_decode(file_get_contents($cache_file), true);
} else {
    $api_url = "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,monero&vs_currencies=usd";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response !== false) {
        $prices = json_decode($response, true);
        file_put_contents($cache_file, json_encode($prices));
    } else {
        die("Error retrieving prices.");
    }
}

// Calculate Vexium value
$stmt = $db->query("SELECT * FROM crypto_reserves");
$cryptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT SUM(wallet_balance) AS total_balance FROM users WHERE role = 'user'");
$total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'];

$total_reserve_value = 0;
foreach ($cryptos as $crypto) {
    $crypto_name = strtolower($crypto['crypto_name']);
    $price = $prices[$crypto_name]['usd'] ?? 0;
    $total_reserve_value += $crypto['reserve_amount'] * $price;
}
$currency_value = ($total_balance > 0) ? $total_reserve_value / $total_balance : 0;

// Initialization
$step = 1;
$errors = [];
$success = false;
$wallet_code = '';
$amount = '';
$unit = 'vexium';
$amount_vexium = 0;
$amount_usd = 0;
$recipient_account = '';

// Anti-resubmit token
if (empty($_SESSION['transfer_token'])) {
    $_SESSION['transfer_token'] = bin2hex(random_bytes(32));
}

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        $wallet_code = strtoupper(trim($_POST['wallet_code']));
        $amount = floatval($_POST['amount']);
        $unit = $_POST['unit'];

        if (empty($wallet_code) || $amount <= 0) {
            $errors[] = "Please fill in all fields correctly.";
        } else {
            if ($amount < 0.05) {
                $errors[] = "The minimum amount is 0.05 USD.";
            } elseif ($amount > 1000000) {
                $errors[] = "The maximum amount is 1,000,000 USD.";
            } else {
                $stmt = $db->prepare("SELECT account_number FROM transactions WHERE wallet_code = :code");
                $stmt->execute(['code' => $wallet_code]);
                $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$recipient) {
                    $errors[] = "The recipient wallet does not exist.";
                } else {
                    $recipient_account = $recipient['account_number'];
                    if ($unit === 'usd') {
                        $amount_vexium = $amount / $currency_value;
                        $amount_usd = $amount;
                    } else {
                        $amount_vexium = $amount;
                        $amount_usd = $amount * $currency_value;
                    }

                    if ($amount_vexium > $balance) {
                        $errors[] = "Insufficient balance.";
                    } else {
                        $step = 2;
                    }
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['transfer_token']) {
            $errors[] = "Invalid or duplicate submission.";
            $step = 1;
        } else {
            unset($_SESSION['transfer_token']); // Remove the token

            $wallet_code = $_POST['wallet_code'];
            $amount_vexium = floatval($_POST['amount_vexium']);
            $recipient_account = $_POST['recipient_account'];
            $entered_pin = $_POST['code_pin'];

            if ($entered_pin != $code_pin) {
                $errors[] = "Incorrect PIN code.";
                $step = 2;
            } else {
                // Transaction
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - :amount WHERE account_number = :account");
                    $stmt->execute([
                        'amount' => $amount_vexium,
                        'account' => $account_number
                    ]);

                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + :amount WHERE account_number = :account");
                    $stmt->execute([
                        'amount' => $amount_vexium,
                        'account' => $recipient_account
                    ]);

                    $db->commit();
                    $success = true;
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Transaction failed.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transfer Vexium</title>
</head>
<body>
    <h1>Transfer Vexium</h1>
    <a href="wallet.php">← Back to Wallet</a>

    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($success): ?>
        <h2>✅ Transfer Completed</h2>
        <p>Amount sent: <?= number_format($amount_vexium, 8) ?> Vexium (<?= number_format($amount_usd, 2) ?> USD)</p>
        <p>To wallet: <?= htmlspecialchars($wallet_code) ?></p>
        <a href="wallet.php">Back to Wallet</a>
    <?php elseif ($step === 1): ?>
        <form method="post">
            <input type="hidden" name="step" value="1">
            <label>Recipient Wallet Code:</label><br>
            <input type="text" name="wallet_code" value="<?= htmlspecialchars($wallet_code) ?>" required><br><br>

            <label>Amount:</label><br>
            <input type="number" name="amount" step="0.00000001" value="<?= htmlspecialchars($amount) ?>" required>
            <select name="unit">
                <option value="vexium" <?= $unit === 'vexium' ? 'selected' : '' ?>>Vexium</option>
                <option value="usd" <?= $unit === 'usd' ? 'selected' : '' ?>>USD</option>
            </select><br><br>

            <button type="submit">Submit Transfer</button>
        </form>
    <?php elseif ($step === 2): ?>
        <h2>Summary</h2>
        <p>Amount in Vexium: <?= number_format($amount_vexium, 8) ?> V</p>
        <p>Amount in USD: <?= number_format($amount_usd, 2) ?> $</p>
        <p>Recipient Wallet: <?= htmlspecialchars($wallet_code) ?></p>

        <form method="post">
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="wallet_code" value="<?= htmlspecialchars($wallet_code) ?>">
            <input type="hidden" name="amount_vexium" value="<?= htmlspecialchars($amount_vexium) ?>">
            <input type="hidden" name="recipient_account" value="<?= htmlspecialchars($recipient_account) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['transfer_token']) ?>">

            <label>PIN Code:</label><br>
            <input type="password" name="code_pin" required><br><br>

            <button type="submit">Send</button>
        </form>
    <?php endif; ?>
</body>
</html>
