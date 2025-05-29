<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

$account_number = $_SESSION['account_number'];

// Vérification du rôle
$stmt = $db->prepare("SELECT role FROM users WHERE account_number = :account_number");
$stmt->execute(['account_number' => $account_number]);
$role = $stmt->fetchColumn();

if (!$role || ($role !== 'user' && $role !== 'superadmin')) {
    echo "Access denied.";
    exit;
}

// Compter les wallets créés dans les dernières 24h
$stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE account_number = :account_number AND created_at >= NOW() - INTERVAL 1 DAY");
$stmt->execute(['account_number' => $account_number]);
$wallet_count = $stmt->fetchColumn();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($wallet_count >= 5) {
        $_SESSION['receive_error'] = "You have reached the limit of 5 temporary wallets in the past 24 hours.";
    } else {
        // Générer un code wallet
        $generated_wallet = strtoupper(bin2hex(random_bytes(8)));

        // Insérer en base
        $stmt = $db->prepare("INSERT INTO transactions (account_number, wallet_code) VALUES (:account_number, :wallet_code)");
        $stmt->execute([
            'account_number' => $account_number,
            'wallet_code' => $generated_wallet
        ]);

        $_SESSION['receive_success'] = $generated_wallet;
    }

    // Redirection pour éviter la régénération au refresh
    header("Location: receive.php");
    exit;
}

// Récupération des messages (si présents)
$success_wallet = $_SESSION['receive_success'] ?? '';
$error_message = $_SESSION['receive_error'] ?? '';

// Nettoyage des sessions temporaires
unset($_SESSION['receive_success'], $_SESSION['receive_error']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receive</title>
    <script>
        // Fonction pour copier le wallet_code dans le presse-papiers
        function copyWalletCode() {
            var walletCode = document.getElementById('wallet_code');
            var textArea = document.createElement('textarea');
            textArea.value = walletCode.textContent;  // Utilise textContent pour récupérer le code
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea); // Nettoyer après la copie
            alert('Wallet code copied to clipboard!');
        }
    </script>
</head>
<body>
    <h1>Receive - Temporary Wallet</h1>
    <a href="wallet.php">Back to Wallet</a>

    <?php if ($error_message): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php elseif ($success_wallet): ?>
        <p>Temporary wallet generated:</p>
        <p><strong id="wallet_code"><?php echo htmlspecialchars($success_wallet); ?></strong></p>
        <!-- Bouton pour copier le code du wallet -->
        <button onclick="copyWalletCode()">Copy Wallet Code</button>
    <?php else: ?>
        <p>You have created <?php echo $wallet_count; ?> / 5 wallets in the last 24 hours.</p>
        <form method="post">
            <button type="submit">Generate Temporary Wallet</button>
        </form>
    <?php endif; ?>
</body>
</html>
