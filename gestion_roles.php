<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is superadmin
if (!isset($_SESSION['account_number']) || $_SESSION['role'] !== 'superadmin') {
    echo "Access denied.";
    exit;
}

// Verify the superadmin password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
    
    // Get the submitted password
    $submitted_password = $_POST['password'] ?? '';
    
    // Retrieve the password from the database
    $stmt = $db->query("SELECT * FROM superadmin_passwords ORDER BY id DESC LIMIT 1");
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($superadmin && password_verify($submitted_password, $superadmin['password_hash'])) {
        $_SESSION['superadmin_authenticated'] = true;
    } else {
        echo "Incorrect password. Access denied.";
        exit;
    }
}

// If the user is authenticated, show the content of the page
if (isset($_SESSION['superadmin_authenticated']) && $_SESSION['superadmin_authenticated']) {
    // Filtering by role
    $filter = $_GET['filter'] ?? 'user';
    $search = $_GET['search'] ?? '';

    include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');

    $users = [];
    if ($filter === 'admin' || ($filter === 'user' && !empty($search))) {
        $query = "SELECT * FROM users WHERE role = :role";
        $params = ['role' => $filter];

        if (!empty($search)) {
            $query .= " AND (pseudo LIKE :search OR account_number LIKE :search)";
            $params['search'] = "%$search%";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // If the user is not authenticated yet, display the password form
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
    <title>Role Management</title>
</head>
<body>
<h1>Superadmin Panel - Role Management</h1>
<?php include('navigation.php'); ?>

<div>
    <a href="?filter=user"><button>👤 Users</button></a>
    <a href="?filter=admin"><button>🛠️ Admins</button></a>
</div>

<form method="GET">
    <input type="text" name="search" placeholder="Search for an account..." value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="filter" value="<?= $filter ?>">
    <button type="submit">🔍 Search</button>
</form>

<?php if ($filter === 'user' && empty($search)): ?>
    <p>🔎 Please perform a search to display a user.</p>
<?php elseif (!empty($users)): ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Username</th>
            <th>Account Number</th>
            <th>Role</th>
            <th>Action</th>
        </tr>

        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['pseudo']) ?></td>
                <td><?= htmlspecialchars($user['account_number']) ?></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
                <td>
                    <!-- Modify username -->
                    <form method="POST" action="/web/traitement_php/superadmin_actions.php" class="inline-form">
                        <input type="hidden" name="account_number" value="<?= $user['account_number'] ?>">
                        <input type="hidden" name="action" value="modify">
                        <input type="text" name="new_pseudo" placeholder="New username">
                        <input type="password" name="superadmin_password" placeholder="Superadmin password">
                        <button type="submit">Modify</button>
                    </form>

                    <!-- Delete user -->
                    <form method="POST" action="/web/traitement_php/superadmin_actions.php" class="inline-form">
                        <input type="hidden" name="account_number" value="<?= $user['account_number'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="password" name="superadmin_password" placeholder="Superadmin password">
                        <button type="submit" onclick="return confirm('Delete this account?')">Delete</button>
                    </form>

                    <!-- Promote or demote -->
                    <?php if ($user['role'] !== 'admin'): ?>
                        <form method="POST" action="/web/traitement_php/superadmin_actions.php" class="inline-form">
                            <input type="hidden" name="account_number" value="<?= $user['account_number'] ?>">
                            <input type="hidden" name="action" value="promote">
                            <input type="password" name="superadmin_password" placeholder="Superadmin password">
                            <button type="submit">Promote to admin</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/web/traitement_php/superadmin_actions.php" class="inline-form">
                            <input type="hidden" name="account_number" value="<?= $user['account_number'] ?>">
                            <input type="hidden" name="action" value="demote">
                            <input type="password" name="superadmin_password" placeholder="Superadmin password">
                            <button type="submit">Demote to user</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>No results found.</p>
<?php endif; ?>

</body>
</html>
