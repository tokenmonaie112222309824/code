<?php
session_start();

// Check if the user is already authenticated as a superadmin
if (!isset($_SESSION['account_number']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "Access Denied.";
    exit;
}

// Verify the superadmin password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
    
    // Retrieve the submitted password
    $submitted_password = $_POST['password'] ?? '';
    
    // Look for the password in the database
    $stmt = $db->query("SELECT * FROM superadmin_passwords ORDER BY id DESC LIMIT 1");
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($superadmin && password_verify($submitted_password, $superadmin['password_hash'])) {
        // If the password is correct, continue to the page
        $_SESSION['superadmin_authenticated'] = true;
    } else {
        // If the password is incorrect
        echo "Incorrect password. Access is denied.";
        exit;
    }
}

// If the user is authenticated, display the page content
if (isset($_SESSION['superadmin_authenticated']) && $_SESSION['superadmin_authenticated']) {
    // Retrieve ongoing blockages with user info
    include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
    $stmt = $db->prepare("
        SELECT pb.*, u.pseudo 
        FROM page_blocks pb
        LEFT JOIN users u ON pb.account_number = u.account_number
        WHERE pb.blocked_at IS NOT NULL
        ORDER BY pb.blocked_at DESC
    ");
    $stmt->execute();
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete a blockage
    if (isset($_GET['delete'])) {
        $blockId = $_GET['delete'];

        // Remove the blockage from the database
        $deleteStmt = $db->prepare("DELETE FROM page_blocks WHERE id = :id");
        $deleteStmt->execute(['id' => $blockId]);

        // Redirect after deletion to prevent resubmission
        header('Location: parametres_systeme.php');
        exit;
    }
} else {
    // If the user is not yet authenticated, show a form for the password
    echo '<form method="POST" action="">
            <label for="password">Superadmin Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Submit</button>
          </form>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
</head>
<body>

<h1>🔧 System Settings (Superadmin)</h1>
<?php include('navigation.php'); ?>
<h3>Access Blocked Pages:</h3>

<!-- Buttons to access blocked pages -->
<form action="block_targeted.php" method="get">
    <button type="submit">Targeted Block (Specific Users)</button>
</form>

<form action="block_emergency.php" method="get">
    <button type="submit">Emergency Block (All Users)</button>
</form>

<h3>Ongoing Blockages:</h3>

<!-- Display ongoing blockages -->
<?php if (count($blocks) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Page</th>
                <th>Title</th>
                <th>Message</th>
                <th>Blocked At</th>
                <th>Targeted User</th> <!-- Added column for the targeted user -->
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blocks as $block): ?>
                <tr>
                    <td><?= htmlspecialchars($block['id']) ?></td>
                    <td><?= htmlspecialchars($block['page_name']) ?></td>
                    <td><?= htmlspecialchars($block['title']) ?></td>
                    <td><?= nl2br(htmlspecialchars($block['message'])) ?></td>
                    <td><?= htmlspecialchars($block['blocked_at']) ?></td>
                    <td>
                        <?php
                        // Display the targeted user or group information
                        if ($block['target_all_users'] == 1) {
                            echo "All Users";
                        } elseif ($block['target_all_admins'] == 1) {
                            echo "All Admins";
                        } else {
                            echo htmlspecialchars($block['pseudo'] ?? 'Unknown');
                        }
                        ?>
                    </td>
                    <td>
                        <!-- Link to delete a blockage -->
                        <a href="?delete=<?= $block['id'] ?>" onclick="return confirm('Are you sure you want to delete this blockage?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No ongoing blockages.</p>
<?php endif; ?>

</body>
</html>
