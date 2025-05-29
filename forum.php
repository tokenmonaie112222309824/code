<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php');

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    echo "Access denied. You must be logged in to use the forum.";
    exit;
}

$account_number = $_SESSION['account_number'];
$role = $_SESSION['role'] ?? '';

// If the user is an admin, block access
if ($role === 'admin') {
    echo "Access denied. Administrators cannot access this page.";
    exit;
}

// The superadmin always has access
if ($role === 'superadmin') {
    // Continue without blocking for the superadmin
}

// Retrieve the username if not in session
if (!isset($_SESSION['pseudo'])) {
    $stmt = $db->prepare("SELECT pseudo FROM users WHERE account_number = :acc");
    $stmt->execute(['acc' => $account_number]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['pseudo'] = $user['pseudo'] ?? 'unknown';
}

// Sending a message
if (isset($_POST['send_message'])) {
    $message_content = trim($_POST['message']);
    $show_pseudo = isset($_POST['show_pseudo']) ? 1 : 0;
    $reply_to = isset($_POST['reply_to']) ? intval($_POST['reply_to']) : null;

    if (!empty($message_content) && strlen($message_content) <= 120) {
        $stmt = $db->prepare("INSERT INTO messages (account_number, message, show_pseudo, reply_to, pseudo) 
                              VALUES (:account_number, :message, :show_pseudo, :reply_to, :pseudo)");
        $stmt->execute([ 
            'account_number' => $account_number, 
            'message' => $message_content, 
            'show_pseudo' => $show_pseudo, 
            'reply_to' => $reply_to ?: null, 
            'pseudo' => $_SESSION['pseudo'] 
        ]);
        header("Location: forum.php");
        exit;
    }
}

// Retrieving messages
$stmt = $db->query("SELECT m.*, u.role AS user_role FROM messages m LEFT JOIN users u ON m.account_number = u.account_number ORDER BY timestamp ASC");
$allMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize parent/reply messages
$messages = [];
foreach ($allMessages as $msg) {
    if ($msg['reply_to'] === null) {
        $messages[$msg['id']] = $msg;
        $messages[$msg['id']]['replies'] = [];
    } else {
        $messages[$msg['reply_to']]['replies'][] = $msg;
    }
}

// Function to display visible author
function getVisibleAuthor($msg) {
    return $msg['show_pseudo'] ? "*********" : htmlspecialchars($msg['pseudo']);
}

// Page block check?
$currentPage = basename($_SERVER['PHP_SELF']);
$stmt = $db->prepare("SELECT * FROM page_blocks WHERE account_number = :acc AND page_name = :page");
$stmt->execute(['acc' => $account_number, 'page' => $currentPage]);
$block = $stmt->fetch(PDO::FETCH_ASSOC);

if ($block) {
    echo "<div style='border: 2px solid red; padding: 15px; margin: 20px 0; background: #ffe5e5;'>
            <h3>" . htmlspecialchars($block['title']) . "</h3>
            <p>" . nl2br(htmlspecialchars($block['message'])) . "</p>
          </div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/forum.css">
</head>
<body>

<h1>Discussion Forum</h1>
<!-- Bouton pour retourner à contact.php -->
<a href="contact.php" class="btn back-button">Go Back to Contact Page</a>

<!-- Conteneur pour les messages -->
<div id="messages-container">
    <?php foreach ($messages as $msg): ?>
        <div class="message">
            <p>
                <strong><?= getVisibleAuthor($msg) ?> :</strong>
                <?= htmlspecialchars($msg['message']) ?><br>
                <small><?= $msg['timestamp'] ?></small>
            </p>

            <?php if (!empty($msg['replies'])): ?>
                <?php foreach ($msg['replies'] as $reply): ?>
                    <div class="reply">
                        <strong><?= getVisibleAuthor($reply) ?> :</strong>
                        <?= htmlspecialchars($reply['message']) ?><br>
                        <small><?= $reply['timestamp'] ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Formulaire de message -->
<h3>Write a message</h3>
<form method="POST" action="forum.php">
    <textarea name="message" rows="3" maxlength="120" required placeholder="Write your message here..."></textarea><br>
    <label><input type="checkbox" name="show_pseudo"> Hide my username</label><br>
    <button type="submit" name="send_message">Send</button>
</form>

<script>
    // Scroll vers le bas de la page
    window.onload = function() {
        var messagesContainer = document.getElementById('messages-container');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    };
</script>

</body>
</html>
