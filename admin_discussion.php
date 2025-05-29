<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Accès réservé aux admin ou superadmin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo "Access restricted to administrators.";
    exit;
}

// Vérification du mot de passe avant d'afficher la page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_password'])) {
        // Vérifier le mot de passe dans la table admin_passwords
        $password = $_POST['admin_password'];
        $stmt = $db->prepare("SELECT * FROM admin_passwords ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $adminPasswordRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminPasswordRecord && password_verify($password, $adminPasswordRecord['password_hash'])) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error_message = "Incorrect password.";
        }
    }
}

if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
    echo '<form method="POST">
            <label for="admin_password">Admin password:</label>
            <input type="password" name="admin_password" required>
            <button type="submit">Submit</button>
          </form>';
    exit;
}

$account_number = $_SESSION['account_number'];
$is_superadmin = $_SESSION['role'] === 'superadmin';

$stmt = $db->prepare("SELECT afm.id, afm.message, afm.timestamp, afm.reply_to, u.pseudo, u.role
                      FROM admin_forum_messages afm
                      JOIN users u ON afm.account_number = u.account_number
                      ORDER BY afm.timestamp ASC");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$structured_messages = [];
foreach ($messages as $message) {
    if ($message['reply_to'] === null) {
        $structured_messages[$message['id']] = ['message' => $message, 'replies' => []];
    } else {
        $structured_messages[$message['reply_to']]['replies'][] = $message;
    }
}

if (isset($_POST['send_message'])) {
    $msg = $_POST['message'];
    $reply_to = !empty($_POST['reply_to']) ? intval($_POST['reply_to']) : null;
    if (!empty($msg)) {
        $stmt = $db->prepare("INSERT INTO admin_forum_messages (account_number, message, reply_to) VALUES (?, ?, ?)");
        $stmt->execute([$account_number, $msg, $reply_to]);
        header("Location: admin_discussion.php");
        exit;
    }
}

if ($is_superadmin && isset($_POST['delete_message'])) {
    $id = intval($_POST['delete_message']);
    $db->prepare("DELETE FROM admin_forum_messages WHERE id = ? OR reply_to = ?")->execute([$id, $id]);
    header("Location: admin_discussion.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Forum</title>
</head>
<body>
    <h1>Internal Admin/Superadmin Forum</h1>
    <?php include('navigation.php'); ?>

    <!-- Display messages -->
    <div>
        <?php foreach ($structured_messages as $main): ?>
            <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;">
                <p><strong><?= htmlspecialchars($main['message']['pseudo']) ?> (<?= $main['message']['role'] ?>)</strong><br>
                   <?= nl2br(htmlspecialchars($main['message']['message'])) ?><br>
                   <small><?= $main['message']['timestamp'] ?></small>
                </p>
                <?php if ($is_superadmin): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="delete_message" value="<?= $main['message']['id'] ?>">
                        <button type="submit">🗑 Delete</button>
                    </form>
                <?php endif; ?>

                <!-- Reply -->
                <form method="POST">
                    <input type="hidden" name="reply_to" value="<?= $main['message']['id'] ?>">
                    <input type="text" name="message" maxlength="120" required placeholder="Reply...">
                    <button type="submit" name="send_message">Reply</button>
                </form>

                <!-- Replies -->
                <?php foreach ($main['replies'] as $reply): ?>
                    <div style="margin-left: 20px; border-left: 2px solid #aaa; padding-left: 10px; margin-top: 10px;">
                        <p><strong><?= htmlspecialchars($reply['pseudo']) ?> (<?= $reply['role'] ?>)</strong><br>
                           <?= nl2br(htmlspecialchars($reply['message'])) ?><br>
                           <small><?= $reply['timestamp'] ?></small>
                        </p>
                        <?php if ($is_superadmin): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="delete_message" value="<?= $reply['id'] ?>">
                                <button type="submit">🗑 Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main form for new message -->
    <form action="admin_discussion.php" method="POST">
        <textarea name="message" rows="3" cols="50" maxlength="120" required placeholder="New message..."></textarea><br>
        <button type="submit" name="send_message">Send</button>
    </form>

</body>
</html>
