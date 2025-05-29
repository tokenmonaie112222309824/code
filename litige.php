<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['account_number'])) {
    echo "Access denied. You must be logged in to open a dispute.";
    exit;
}

$account_number = $_SESSION['account_number'];
$role = $_SESSION['role'] ?? '';

// Si l'utilisateur est un admin, refuser l'accès
if ($role === 'admin') {
    echo "Access denied. Administrators cannot access this page.";
    exit;
}

// Le superadmin a toujours accès
if ($role === 'superadmin') {
    // Continue sans bloquer pour le superadmin
}

// Récupérer les litiges existants ouverts par l'utilisateur
$stmt = $db->prepare("SELECT * FROM disputes WHERE account_number = :account_number ORDER BY timestamp DESC");
$stmt->execute(['account_number' => $account_number]);
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si le formulaire de nouveau litige est soumis
if (isset($_POST['submit_dispute'])) {
    $dispute_type = $_POST['dispute_type'];
    $description = $_POST['description'];
    $password = $_POST['password'];
    $captcha_input = $_POST['captcha'] ?? ''; // Récupérer la réponse du CAPTCHA

    // Vérification du CAPTCHA
    if (!isset($_SESSION['captcha_solution']) || $captcha_input !== $_SESSION['captcha_solution']) {
        $error_message = "Incorrect CAPTCHA. Please try again.";
    } else {
        // Vérifier le mot de passe
        $stmt = $db->prepare("SELECT * FROM users WHERE account_number = :account_number");
        $stmt->execute(['account_number' => $account_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($password, $user['password'])) {
            // Insérer le litige dans la base de données
            $stmt = $db->prepare("INSERT INTO disputes (account_number, dispute_type, description, status) 
                                  VALUES (:account_number, :dispute_type, :description, 'pending')");
            $stmt->execute([
                'account_number' => $account_number,
                'dispute_type' => $dispute_type,
                'description' => $description
            ]);

            $success_message = "Your dispute has been successfully opened. You will be contacted by an admin.";

            // Rediriger pour éviter les soumissions multiples
            header("Location: litige.php");
            exit;
        } else {
            $error_message = "Incorrect password.";
        }
    }
}

// Générer un CAPTCHA si nécessaire
if (!isset($_SESSION['captcha_image'])) {
    $query = "SELECT * FROM captcha ORDER BY RAND() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $captcha = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['captcha_image'] = $captcha['image_path'];
    $_SESSION['captcha_solution'] = $captcha['solution'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/litige.css">
</head>
<body>
    <h1>Disputes</h1>
    <a href="contact.php" class="btn back-button">Go Back to Contact Page</a>

    <!-- Afficher le message de succès -->
    <?php if (!empty($success_message)): ?>
        <p style="color:green;"><?= $success_message ?></p>
    <?php endif; ?>

    <!-- Afficher le message d'erreur -->
    <?php if (!empty($error_message)): ?>
        <p style="color:red;"><?= $error_message ?></p>
    <?php endif; ?>

    <!-- Liste des litiges existants -->
    <h2>Open Disputes:</h2>
    <ul>
        <?php foreach ($disputes as $dispute): ?>
            <li>
                <strong><?= htmlspecialchars($dispute['dispute_type']) ?></strong>
                <br><small>Status: <?= $dispute['status'] ?></small>
                <br><a href="discussion.php?dispute_id=<?= $dispute['id'] ?>">View Discussion</a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Formulaire pour ouvrir un nouveau litige -->
    <h2>Open a New Dispute:</h2>
    <form action="litige.php" method="POST">
        <label for="dispute_type">Select an issue:</label>
        <select name="dispute_type" id="dispute_type" required>
            <option value="transaction_disputes">Transaction Disputes</option>
            <option value="fraud_attempts">Fraud or Phishing Attempts</option>
            <option value="withdrawal_issues">Withdrawal or Cancellation Issues</option>
            <option value="conversion_rates">Internal Currency Conversion Rates</option>
            <option value="account_security">Account Security</option>
            <option value="forum_behavior">Inappropriate Forum Behavior</option>
            <option value="liquidity_issues">Liquidity Issues / Internal Currency Volatility</option>
            <option value="geographical_restrictions">Geographical or Legal Restrictions</option>
            <option value="account_verification">Account Verification Issues</option>
            <option value="api_integration">API or External Integration Issues</option>
            <option value="transaction_delays">Transaction Processing Delays</option>
            <option value="other">Other</option>
        </select><br><br>

        <label for="description">Describe the issue:</label>
        <textarea name="description" id="description" rows="4" cols="50" required></textarea><br><br>

        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required><br><br>

        <!-- Afficher l'image CAPTCHA -->
        <?php if (isset($_SESSION['captcha_image'])): ?>
            <div class="captcha-container">
                <img src="/web/assets/im/<?= $_SESSION['captcha_image'] ?>" alt="Captcha" class="captcha-img"><br>
                <label for="captcha">Enter the CAPTCHA:</label>
                <input type="text" name="captcha" id="captcha" required><br><br>

                <!-- Affichage de l'erreur CAPTCHA si présente -->
                <?php if (isset($captcha_error)): ?>
                    <div class="captcha-error"><?= $captcha_error ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <button type="submit" name="submit_dispute">Open Dispute</button>
    </form>
</body>
</html>
