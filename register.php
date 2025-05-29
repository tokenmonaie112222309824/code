<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

// 🔒 If the user has already been banned by the honeypot
if (isset($_SESSION['honeypot_ban']) && $_SESSION['honeypot_ban'] === true) {
    die("⛔ Access denied. You are banned.");
}

// Check if the user is temporarily banned
$ip = $_SERVER['REMOTE_ADDR'];
if (isset($_SESSION['banned_ips'][$ip]) && time() < $_SESSION['banned_ips'][$ip]) {
    die("⛔ You are temporarily banned. Please try again later.");
}

// Manual unban button for testing
if (isset($_POST['dev_unban'])) {
    unset($_SESSION['honeypot_ban']);
    unset($_SESSION['captcha_attempts']);
    unset($_SESSION['banned_ips']);
    unset($_SESSION['last_captcha_input']);
    echo "<div style='color:green;font-weight:bold;'>✅ Session unblocked. You can try again.</div>";
    exit;
}

include('../config/database.php');

// 🐝 Robot detection (honeypot)
if (!empty($_POST['honeypot'])) {
    echo "<div class='error-message'>⛔ Suspicious activity detected. You are blocked.</div>";
    $_SESSION['honeypot_ban'] = true;
    exit;
}

// Initialize form variables (reset them if CAPTCHA fails)
$formFields = ['pseudo', 'secret_pseudo', 'password', 'pin', 'captcha'];
foreach ($formFields as $field) {
    if (!isset($_SESSION['form'][$field])) {
        $_SESSION['form'][$field] = ''; // Default empty value
    }
}

// Lors de la soumission du formulaire, mettre à jour les données
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formFields as $field) {
        if (isset($_POST[$field])) {
            $_SESSION['form'][$field] = $_POST[$field];
        }
    }
}

// Captcha attempts handling
if (!isset($_SESSION['captcha_attempts'])) {
    $_SESSION['captcha_attempts'] = 0;
}

// If CAPTCHA image is not yet set, generate one
if (!isset($_SESSION['captcha_image'])) {
    $query = "SELECT * FROM captcha ORDER BY RAND() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $captcha = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['captcha_image'] = $captcha['image_path'];
    $_SESSION['captcha_solution'] = $captcha['solution'];
}

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $captcha_input = $_POST['captcha'] ?? '';

    // Vérification du CAPTCHA
    if (!isset($_SESSION['captcha_solution']) || $captcha_input !== $_SESSION['captcha_solution']) {
        // On ne compte pas la même tentative si l'utilisateur soumet la même réponse de CAPTCHA
        if (!isset($_SESSION['last_captcha_input']) || $_SESSION['last_captcha_input'] !== $captcha_input) {
            $_SESSION['captcha_attempts']++;
            $_SESSION['last_captcha_input'] = $captcha_input;
        }

        if ($_SESSION['captcha_attempts'] >= 3) {
            $_SESSION['banned_ips'][$ip] = time() + (30 * 60); // Bannir pour 30 minutes
            header("Location: ban.php");
            exit;
        } else {
            $remaining = 3 - $_SESSION['captcha_attempts'];
            $captcha_error = "Incorrect captcha. You have $remaining attempt(s) left.";

            // Regénérer une nouvelle image CAPTCHA en cas d'erreur
            $query = "SELECT * FROM captcha ORDER BY RAND() LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $captcha = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['captcha_image'] = $captcha['image_path'];
            $_SESSION['captcha_solution'] = $captcha['solution'];
        }
    } else {
        // Successful registration
        $pseudo = $_SESSION['form']['pseudo'];
        $secret_pseudo = $_SESSION['form']['secret_pseudo'];
        $password = $_SESSION['form']['password'];
        $pin = $_SESSION['form']['pin'];

        $check = $db->prepare("SELECT * FROM users WHERE pseudo = :pseudo OR secret_pseudo = :secret");
        $check->execute(['pseudo' => $pseudo, 'secret' => $secret_pseudo]);

        if ($check->rowCount() > 0) {
            $error_message = "Pseudo or secret pseudo already in use.";
        } else {
            // Generate a unique account number
            do {
                $account_number = mt_rand(1000000000000000, 9999999999999999);
                $check_account = $db->prepare("SELECT COUNT(*) FROM users WHERE account_number = :acc");
                $check_account->execute(['acc' => $account_number]);
                $exists = $check_account->fetchColumn();
            } while ($exists > 0); // Ensure the account number is unique
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into the database with a default user role
            $insert = $db->prepare("INSERT INTO users (pseudo, secret_pseudo, password, code_pin, account_number, role)
                VALUES (:pseudo, :secret, :pwd, :pin, :acc, 'user')");
            $insert->execute([
                'pseudo' => $pseudo,
                'secret' => $secret_pseudo,
                'pwd' => $hashed_password,
                'pin' => $pin,
                'acc' => $account_number
            ]);

            // ✅ Reset everything
            $_SESSION['captcha_attempts'] = 0;
            unset($_SESSION['captcha_loaded'], $_SESSION['captcha_id'], $_SESSION['captcha_solution'], $_SESSION['captcha_image'], $_SESSION['last_captcha_input']);
            unset($_SESSION['form']); // Clear the form session data
            $_SESSION['account_number'] = $account_number;

            header("Location: confirm_account.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/register.css">
    <title>Sign Up</title>
</head>
<body>
    <!-- Conteneur principal du formulaire -->
    <div class="container">
        <!-- Header avec logo et titre -->
        <div class="header">
            <img src="../assets/logo.PNG" alt="Logo" class="logo">
            <h1>Sign Up</h1>
        </div>

        <!-- Formulaire d'inscription -->
        <form method="POST">
            <input type="text" name="pseudo" placeholder="Pseudo" value="<?= htmlspecialchars($_SESSION['form']['pseudo']) ?>" required><br>
            <?= isset($error_message) ? "<div class='error-message'>$error_message</div>" : '' ?>

            <input type="text" name="secret_pseudo" placeholder="Secret Pseudo" value="<?= htmlspecialchars($_SESSION['form']['secret_pseudo']) ?>" required><br>
            <?= isset($error_message) ? "<div class='error-message'>$error_message</div>" : '' ?>

            <input type="password" name="password" placeholder="Password (min 8 characters)" value="<?= htmlspecialchars($_SESSION['form']['password']) ?>" required><br>
            <?= isset($error_message) ? "<div class='error-message'>$error_message</div>" : '' ?>

            <input type="text" name="pin" placeholder="PIN Code (6 digits)" value="<?= htmlspecialchars($_SESSION['form']['pin']) ?>" required><br><br>

            <!-- Afficher l'image CAPTCHA dès le début -->
            <?php if (isset($_SESSION['captcha_image'])): ?>
                <img src="/web/assets/im/<?= $_SESSION['captcha_image'] ?>" alt="Captcha" class="captcha-img"><br>

            <?php endif; ?>

            <input type="text" name="captcha" placeholder="Enter the captcha" required><br>
            <?= isset($captcha_error) ? "<div class='error-message'>$captcha_error</div>" : '' ?>

            <!-- Case à cocher pour accepter les conditions d'utilisation -->
            <label>
                <input type="checkbox" name="accept_terms" required>
                I accept the <a href="terms_of_use.html" target="_blank">Terms of Use</a>
            </label><br><br>

            <button type="submit" name="register">Sign Up</button>
            <p>Do you have an account? <a href="login.php">Log in</a>.</p>
        </form>

        <!-- Champ Honeypot caché -->
        <div style="display:none;">
            <label for="honeypot">Don't bot</label>
            <input type="text" name="honeypot" id="honeypot" autocomplete="off">
        </div>
    </div>
</body>
</html>
