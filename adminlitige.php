<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Access restricted to admin or superadmin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../register.php");
    exit;
}

// Password check before showing the page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_password'])) {
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

// Fetch disputes
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';

$stmt = $db->prepare("SELECT * FROM disputes WHERE status = :status ORDER BY timestamp DESC");
$stmt->execute(['status' => $statusFilter]);
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Change status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $newStatus = $_POST['status'];
    $disputeId = $_POST['dispute_id'];

    $stmt = $db->prepare("UPDATE disputes SET status = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $disputeId]);

    header("Location: adminlitige.php?status=" . $statusFilter);
    exit;
}

// Delete dispute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_dispute'])) {
    $disputeIdToDelete = $_POST['dispute_id'];

    $deleteMessagesStmt = $db->prepare("DELETE FROM dispute_messages WHERE dispute_id = :dispute_id");
    $deleteMessagesStmt->execute(['dispute_id' => $disputeIdToDelete]);

    $deleteDisputeStmt = $db->prepare("DELETE FROM disputes WHERE id = :id");
    $deleteDisputeStmt->execute(['id' => $disputeIdToDelete]);

    header("Location: adminlitige.php?status=" . $statusFilter);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Disputes</title>
</head>
<body>
<h1>Disputes (<?= htmlspecialchars($statusFilter) ?>)</h1>
<?php include('navigation.php'); ?>

<!-- Filter buttons -->
<div>
    <a href="?status=pending"><button>Pending</button></a>
    <a href="?status=resolved"><button>Resolved</button></a>
    <a href="?status=closed"><button>Closed</button></a>
</div>

<table border="1" cellpadding="10" style="margin-top: 20px;">
    <thead>
        <tr>
            <th>ID</th>
            <th>Account Number</th>
            <th>Type</th>
            <th>Description</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($disputes as $dispute): ?>
            <tr>
                <td><?= htmlspecialchars($dispute['id']) ?></td>
                <td><?= htmlspecialchars($dispute['account_number']) ?></td>
                <td><?= htmlspecialchars($dispute['dispute_type']) ?></td>
                <td><?= htmlspecialchars($dispute['description']) ?></td>
                <td><?= htmlspecialchars($dispute['timestamp']) ?></td>
                <td>
                    <a href="adminlitige_discussion.php?id=<?= $dispute['id'] ?>">View</a>
                    
                    <!-- Status change form -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">
                        <select name="status">
                            <option value="pending" <?= $dispute['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="resolved" <?= $dispute['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= $dispute['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                        <button type="submit" name="change_status">Change</button>
                    </form>
                    
                    <!-- Delete form -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="dispute_id" value="<?= $dispute['id'] ?>">
                        <button type="submit" name="delete_dispute" onclick="return confirm('Are you sure you want to delete this dispute and all associated messages?')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
