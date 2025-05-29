<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    echo "You must be logged in to access your profile.";
    exit;
}

$account_number = $_SESSION['account_number'];
$error_message = '';
$success_message = '';

// Retrieve the user's information
$stmt = $db->prepare("SELECT * FROM users WHERE account_number = :account_number");
$stmt->execute(['account_number' => $account_number]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

// Initialize form variables (reset them if CAPTCHA fails)
$formFields = ['new_pseudo', 'new_secret_pseudo', 'new_password', 'new_codepin', 'captcha'];
foreach ($formFields as $field) {
    if (!isset($_SESSION['form'][$field])) {
        $_SESSION['form'][$field] = ''; // Default empty value
    }
}

// CAPTCHA handling
if (!isset($_SESSION['captcha_attempts'])) {
    $_SESSION['captcha_attempts'] = 0;
}

if (!isset($_SESSION['captcha_image'])) {
    $query = "SELECT * FROM captcha ORDER BY RAND() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $captcha = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['captcha_image'] = $captcha['image_path'];
    $_SESSION['captcha_solution'] = $captcha['solution'];
}

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formFields as $field) {
        if (isset($_POST[$field])) {
            $_SESSION['form'][$field] = $_POST[$field];
        }
    }

    $captcha_input = $_POST['captcha'] ?? '';

    // CAPTCHA verification
    if (!isset($_SESSION['captcha_solution']) || $captcha_input !== $_SESSION['captcha_solution']) {
        if (!isset($_SESSION['last_captcha_input']) || $_SESSION['last_captcha_input'] !== $captcha_input) {
            $_SESSION['captcha_attempts']++;
            $_SESSION['last_captcha_input'] = $captcha_input;
        }

        if ($_SESSION['captcha_attempts'] >= 3) {
            $_SESSION['banned_ips'][$_SERVER['REMOTE_ADDR']] = time() + (30 * 60); // Ban for 30 minutes
            header("Location: ban.php");
            exit;
        } else {
            $remaining = 3 - $_SESSION['captcha_attempts'];
            $captcha_error = "Incorrect captcha. You have $remaining attempt(s) left.";

            // Regenerate CAPTCHA if error occurs
            $query = "SELECT * FROM captcha ORDER BY RAND() LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $captcha = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['captcha_image'] = $captcha['image_path'];
            $_SESSION['captcha_solution'] = $captcha['solution'];
        }
    } else {
        // Proceed with updating the user profile
        $current_password = $_POST['current_password'];
        $current_codepin = $_POST['current_codepin'];

        // Verify the current password and code pin
        if (password_verify($current_password, $user['password']) && $current_codepin == $user['code_pin']) {
            $new_pseudo = $_POST['new_pseudo'] ? $_POST['new_pseudo'] : $user['pseudo'];
            $new_secret_pseudo = $_POST['new_secret_pseudo'] ? $_POST['new_secret_pseudo'] : $user['secret_pseudo'];
            $new_password = $_POST['new_password'] ? password_hash($_POST['new_password'], PASSWORD_DEFAULT) : $user['password'];
            $new_codepin = $_POST['new_codepin'] ? $_POST['new_codepin'] : $user['code_pin'];

            // Update the user's information
            $stmt = $db->prepare("UPDATE users SET pseudo = :new_pseudo, secret_pseudo = :new_secret_pseudo, password = :new_password, code_pin = :new_codepin WHERE account_number = :account_number");
            $stmt->execute([
                'new_pseudo' => $new_pseudo,
                'new_secret_pseudo' => $new_secret_pseudo,
                'new_password' => $new_password,
                'new_codepin' => $new_codepin,
                'account_number' => $account_number
            ]);

            $success_message = "Changes have been successfully made.";
        } else {
            $error_message = "Incorrect password or PIN code.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/profil.css">
</head>
<body>
    <h1>Profile</h1>
    <?php include('navigation.php'); ?>

    <!-- Display messages -->
    <?php if (!empty($success_message)): ?>
        <p style="color:green;"><?= $success_message ?></p>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <p style="color:red;"><?= $error_message ?></p>
    <?php endif; ?>

    <form action="profil.php" method="POST">
        <h2>Modify Profile</h2>

        <label for="new_pseudo">New Username:</label>
        <input type="text" name="new_pseudo" id="new_pseudo" value="<?= htmlspecialchars($user['pseudo']) ?>"><br><br>

        <label for="new_secret_pseudo">New Secret Username (optional):</label>
        <input type="text" name="new_secret_pseudo" id="new_secret_pseudo" value=""><br><br>

        <label for="new_password">New Password (optional):</label>
        <input type="password" name="new_password" id="new_password"><br><br>

        <label for="new_codepin">New PIN Code (optional):</label>
        <input type="password" name="new_codepin" id="new_codepin"><br><br>

        <h3>Verification</h3>

        <label for="current_password">Current Password:</label>
        <input type="password" name="current_password" id="current_password" required><br><br>

        <label for="current_codepin">Current PIN Code:</label>
        <input type="password" name="current_codepin" id="current_codepin" required><br><br>

        <h3>Captcha Verification</h3>
        <?php if (isset($_SESSION['captcha_image'])): ?>
            <img src="/web/assets/im/<?= $_SESSION['captcha_image'] ?>" alt="Captcha" class="captcha-img"><br>

        <?php endif; ?>
        <input type="text" name="captcha" placeholder="Enter the captcha" required><br>
        <?= isset($captcha_error) ? "<div class='error-message'>$captcha_error</div>" : '' ?>

        <button type="submit" name="submit_changes">Save Changes</button>
    </form>

</body>
</html>
