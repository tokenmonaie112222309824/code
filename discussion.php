<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    echo "You must be logged in to discuss a dispute.";
    exit;
}

$account_number = $_SESSION['account_number'];
$error_message = '';
$success_message = '';

// Check if a dispute ID is passed as a parameter
if (!isset($_GET['dispute_id'])) {
    echo "Dispute not found.";
    exit;
}

$dispute_id = $_GET['dispute_id'];

// Retrieve dispute information
$stmt = $db->prepare("SELECT * FROM disputes WHERE id = :dispute_id AND (account_number = :account_number OR status = 'open')");
$stmt->execute(['dispute_id' => $dispute_id, 'account_number' => $account_number]);
$dispute = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispute) {
    echo "Dispute not found or you are not authorized to access it.";
    exit;
}

// Retrieve the dispute messages
$stmt = $db->prepare("SELECT * FROM dispute_messages WHERE dispute_id = :dispute_id ORDER BY timestamp ASC");
$stmt->execute(['dispute_id' => $dispute_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If the message submission form is submitted
if (isset($_POST['submit_message'])) {
    $password = $_POST['password'];
    $message = $_POST['message'];

    // Check the password
    $stmt = $db->prepare("SELECT * FROM users WHERE account_number = :account_number");
    $stmt->execute(['account_number' => $account_number]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($password, $user['password'])) {
        // Insert the message into the database
        $stmt = $db->prepare("INSERT INTO dispute_messages (dispute_id, account_number, message) VALUES (:dispute_id, :account_number, :message)");
        $stmt->execute([
            'dispute_id' => $dispute_id,
            'account_number' => $account_number,
            'message' => $message
        ]);

        $success_message = "Message sent successfully.";
        // Redirect to avoid multiple submissions
        header("Location: discussion.php?dispute_id=$dispute_id");
        exit;
    } else {
        $error_message = "Incorrect password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/discussion.css">
    <title>Dispute Discussion</title>
</head>
<body>
    <div class="container">
        <h1>Dispute Discussion</h1>
        <?php include('navigation.php'); ?>

        <!-- Messages de confirmation ou d'erreur -->
        <?php if (!empty($success_message)): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>

<!-- Affichage des messages de litige -->
<h2>Messages :</h2>
<div class="messages-box">
    <table>
        <tr>
            <th>Auteur</th>
            <th>Message</th>
            <th>Date</th>
        </tr>
        <?php foreach ($messages as $message): ?>
            <tr>
                <td>
                    <?php
                    $stmt = $db->prepare("SELECT pseudo FROM users WHERE account_number = :account_number");
                    $stmt->execute(['account_number' => $message['account_number']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($user['pseudo']);
                    ?>
                </td>
                <td><?= htmlspecialchars($message['message']) ?></td>
                <td><?= $message['timestamp'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>


        <!-- Formulaire de réponse -->
        <h2>Envoyer un message :</h2>
        <form action="discussion.php?dispute_id=<?= $dispute_id ?>" method="POST">
            <label for="message">Message :</label>
            <textarea name="message" id="message" rows="4" required></textarea><br>

            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required><br>

            <button type="submit" name="submit_message">Envoyer</button>
        </form>
    </div>
</body>
</html>
