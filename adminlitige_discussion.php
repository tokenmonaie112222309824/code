<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Secure access: only admin or superadmin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: register.php");
    exit;
}

// Get dispute ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Missing dispute ID.";
    exit;
}
$disputeId = intval($_GET['id']);

// Fetch dispute data
$stmt = $db->prepare("SELECT * FROM disputes WHERE id = ?");
$stmt->execute([$disputeId]);
$dispute = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispute) {
    echo "Dispute not found.";
    exit;
}

// Message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_message'])) {
    $message = trim($_POST['admin_message']);
    if (!empty($message)) {
        $stmt = $db->prepare("INSERT INTO dispute_messages (dispute_id, account_number, message) 
                              VALUES (:dispute_id, :account_number, :message)");
        $stmt->execute([
            'dispute_id' => $disputeId,
            'account_number' => $_SESSION['account_number'], // Admin account number
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        ]);
        header("Location: adminlitige_discussion.php?id=" . $disputeId);
        exit;
    }
}

// Fetch messages
$stmt = $db->prepare("SELECT * FROM dispute_messages WHERE dispute_id = ? ORDER BY timestamp ASC");
$stmt->execute([$disputeId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispute Discussion #<?= htmlspecialchars($disputeId) ?></title>
</head>
<body>
<?php include('navigation.php'); ?>

<h1>Dispute #<?= htmlspecialchars($disputeId) ?> - <?= htmlspecialchars($dispute['dispute_type']) ?></h1>
<p><strong>Status:</strong> <?= htmlspecialchars($dispute['status']) ?></p>
<p><strong>Description:</strong> <?= nl2br(htmlspecialchars($dispute['description'])) ?></p>

<hr>

<h2>Messages</h2>
<?php if ($messages): ?>
    <?php foreach ($messages as $msg): ?>
        <div style="margin-bottom: 10px;">
            <strong><?= ($msg['account_number'] === $_SESSION['account_number']) ? 'admin' : 'user' ?></strong>
            <small><?= htmlspecialchars($msg['timestamp']) ?></small><br>
            <?= nl2br(htmlspecialchars($msg['message'])) ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No messages for this dispute.</p>
<?php endif; ?>

<hr>

<h3>Send a Message</h3>
<form method="POST">
    <textarea name="admin_message" rows="4" cols="50" placeholder="Your message" required></textarea><br>
    <button type="submit">Send</button>
</form>

</body>
</html>
