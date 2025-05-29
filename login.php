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
$formFields = ['secret_pseudo', 'password', 'captcha'];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
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
        // Check credentials in the database
        $secret_pseudo = $_SESSION['form']['secret_pseudo'];
        $password = $_SESSION['form']['password'];

        $stmt = $db->prepare("SELECT * FROM users WHERE secret_pseudo = :secret");
        $stmt->execute(['secret' => $secret_pseudo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['account_number'] = $user['account_number'];
            $_SESSION['role'] = $user['role']; // Store the role in the session
            $_SESSION['captcha_attempts'] = 0;
            unset($_SESSION['captcha_solution'], $_SESSION['captcha_image'], $_SESSION['last_captcha_input']);
            header("Location: wallet.php"); // Redirect to a page (change as needed)
            exit;
        } else {
            $error_message = "Incorrect credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/login.css">
    <title>Login</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../assets/logo.PNG" alt="Logo" class="logo">
            <h1>Login</h1>
        </div>

        <!-- Afficher l'erreur si des identifiants sont incorrects -->
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="text" name="secret_pseudo" placeholder="Secret Pseudo" value="<?= htmlspecialchars($_SESSION['form']['secret_pseudo']) ?>" required><br>

            <input type="password" name="password" placeholder="Password" value="<?= htmlspecialchars($_SESSION['form']['password']) ?>" required><br>

            <!-- Afficher l'image CAPTCHA -->
            <?php if (isset($_SESSION['captcha_image'])): ?>
                <img src="/web/assets/im/<?= $_SESSION['captcha_image'] ?>" alt="Captcha" class="captcha-img"><br>
            <?php endif; ?>

            <input type="text" name="captcha" placeholder="Enter the captcha" required><br>
            <?= isset($captcha_error) ? "<div class='error-message'>$captcha_error</div>" : '' ?>

            <button type="submit" name="login">Login</button>
            <p>Don't have an account? <a href="register.php">Register here</a>.</p>
        </form>

        <!-- Champ Honeypot caché -->
        <div style="display:none;">
            <label for="honeypot">Don't bot</label>
            <input type="text" name="honeypot" id="honeypot" autocomplete="off">
        </div>
    </div>
</body>
</html>
