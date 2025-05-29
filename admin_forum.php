<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Restricted access to admin or superadmin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../register.php");
    exit;
}

// Password verification before displaying the page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_password'])) {
        // Verify the password in the admin_passwords table
        $password = $_POST['admin_password'];
        $stmt = $db->prepare("SELECT * FROM admin_passwords ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $adminPasswordRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminPasswordRecord && password_verify($password, $adminPasswordRecord['password_hash'])) {
            // Correct password, continue displaying the page
            $_SESSION['admin_authenticated'] = true;
        } else {
            // Incorrect password
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

// If the admin is authenticated, retrieve the forum messages
$stmt = $db->prepare("SELECT messages.id, users.pseudo, users.account_number, messages.message, messages.show_pseudo, messages.timestamp 
                      FROM messages 
                      JOIN users ON messages.account_number = users.account_number 
                      WHERE messages.reply_to IS NULL  -- Only retrieve main messages
                      ORDER BY messages.timestamp DESC");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Deleting a message
if (isset($_GET['delete_message'])) {
    $message_id = $_GET['delete_message'];
    $stmt = $db->prepare("DELETE FROM messages WHERE id = :id");
    $stmt->execute(['id' => $message_id]);
    header("Location: admin_forum.php");
    exit;
}

// Deleting a reply
if (isset($_GET['delete_reply'])) {
    $reply_id = $_GET['delete_reply'];
    $stmt = $db->prepare("DELETE FROM messages WHERE id = :id");
    $stmt->execute(['id' => $reply_id]);
    header("Location: admin_forum.php");
    exit;
}

// Editing a reply
if (isset($_POST['edit_reply_message']) && isset($_POST['reply_id'])) {
    $edited_content = $_POST['edit_reply_message'];
    $reply_id = $_POST['reply_id'];

    if (!empty($edited_content)) {
        // Update the reply message
        $stmt = $db->prepare("UPDATE messages SET message = :message WHERE id = :id");
        $stmt->execute(['message' => $edited_content, 'id' => $reply_id]);
        header("Location: admin_forum.php");
        exit;
    }
}

// Replying to a message
if (isset($_POST['reply_message']) && isset($_POST['message_id'])) {
    $reply_content = $_POST['reply_message'];
    $message_id = $_POST['message_id'];
    $account_number = $_SESSION['account_number'];

    if (!empty($reply_content)) {
        // Admin's reply
        $stmt = $db->prepare("INSERT INTO messages (account_number, message, show_pseudo, reply_to) 
                              VALUES (:account_number, :message, 0, :reply_to)");  // show_pseudo = 0 to hide the pseudo
        $stmt->execute([
            'account_number' => $account_number,
            'message' => $reply_content,
            'reply_to' => $message_id  // Reply linked to this message
        ]);
        header("Location: admin_forum.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Forum</title>
</head>
<body>
    <h1>Admin Forum</h1>
    <?php include('navigation.php'); ?>
    
    <!-- Display messages -->
    <div>
        <?php foreach ($messages as $message): ?>
            <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;">
                <p>
                    <?php if ($message['show_pseudo'] == 0): ?>
                        <strong><?= htmlspecialchars($message['pseudo']) ?> (Account Number: <?= htmlspecialchars($message['account_number']) ?>) :</strong>
                    <?php else: ?>
                        <strong>[Pseudo Hidden] (Account Number: <?= htmlspecialchars($message['account_number']) ?>) :</strong>
                    <?php endif; ?>
                    <?= htmlspecialchars($message['message']) ?>
                    <br><small><?= $message['timestamp'] ?></small>
                </p>

                <!-- Actions under the message -->
                <a href="?delete_message=<?= $message['id'] ?>" style="color: red; margin-right: 10px;">Delete</a>
                
                <!-- Reply form -->
                <form action="admin_forum.php" method="POST" style="margin-top: 10px;">
                    <textarea name="reply_message" rows="3" cols="50" required placeholder="Reply to this message"></textarea><br><br>
                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                    <button type="submit">Reply</button>
                </form>

                <!-- Display replies (associated messages) -->
                <?php
                // Retrieve the admin's replies to this message
                $stmt_replies = $db->prepare("SELECT messages.id, messages.message, messages.timestamp, users.pseudo 
                                              FROM messages 
                                              JOIN users ON messages.account_number = users.account_number
                                              WHERE messages.reply_to = :message_id 
                                              ORDER BY messages.timestamp ASC");
                $stmt_replies->execute(['message_id' => $message['id']]);
                $replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);

                foreach ($replies as $reply): ?>
                    <div style="margin-left: 20px; padding: 10px; border: 1px solid #ddd; background-color: #f4f4f4;">
                        <strong>Admin (Reply) :</strong>
                        <?= htmlspecialchars($reply['message']) ?>
                        <br><small>Reply sent by <?= htmlspecialchars($reply['pseudo']) ?> - <?= $reply['timestamp'] ?></small>
                        
                        <!-- Edit or delete a reply -->
                        <a href="?delete_reply=<?= $reply['id'] ?>" style="color: red; margin-left: 10px;">Delete</a>
                        <a href="#" onclick="document.getElementById('edit_reply_<?= $reply['id'] ?>').style.display='block'; return false;" style="color: blue; margin-left: 10px;">Edit</a>

                        <!-- Edit reply form -->
                        <form id="edit_reply_<?= $reply['id'] ?>" action="admin_forum.php" method="POST" style="display: none; margin-top: 10px;">
                            <textarea name="edit_reply_message" rows="3" cols="50" required><?= htmlspecialchars($reply['message']) ?></textarea><br><br>
                            <input type="hidden" name="reply_id" value="<?= $reply['id'] ?>">
                            <button type="submit">Update Reply</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
