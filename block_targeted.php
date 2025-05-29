<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Superadmin check
if (!isset($_SESSION['account_number']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "Access denied.";
    exit;
}

// Search user/admin
$searchResults = [];
if (isset($_GET['search']) && !empty($_GET['query'])) {
    $query = "%" . trim($_GET['query']) . "%";
    // Modify query to exclude superadmins
    $stmt = $db->prepare("SELECT account_number, pseudo, role FROM users WHERE (pseudo LIKE :query OR account_number LIKE :query) AND role != 'superadmin'");
    $stmt->execute(['query' => $query]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Updated list of pages to block
$pagesList = [
    'admin_discussion.php' => '👥 Admin Internal Discussion',
    'admin_forum.php' => '🛠️ Admin Forum',
    'admin_reserves.php' => '📦 Crypto Reserves',
    'adminlitige.php' => '⚖️ Manage Disputes',
    'adminlitige_discussion.php' => '🗨️ Dispute - Discussion',
    'buy.php' => '💸 Buy Currency',
    'buy_sell.php' => '💸 Buy/Sell',
    'confirm_account.php' => '✅ Account Confirmation',
    'ban.php' => '🔒 Ban User',
    'contact.php' => '💬 Chat',
    'discussion.php' => '🗣️ User Discussion',
    'forum.php' => '💬 Public Forum',
    'gestion_roles.php' => '🛡️ Role Management',
    'litige.php' => '⚠️ Create Dispute',
    'login.php' => '🔑 Login',
    'parametres_systeme.php' => '🔧 System Settings',
    'profil.php' => '📢 Profile',
    'purchase.php' => '💰 Manage Orders',
    'receive.php' => '📥 Receive',
    'register.php' => '📝 Registration',
    'searchuser.php' => '🔍 User Management',
    'sell.php' => '💰 Sell Currency',
    'transaction_superadmin.php' => '📊 Transactions (Superadmin)',
    'transfer.php' => '🔁 Transfer',
    'valeur.php' => '📉 Currency Value',
    'wallet.php' => '💼 Wallet'
];

// If the targeted block form is submitted
if (isset($_POST['start_block'])) {
    $target = $_POST['target'] ?? null;
    $pages = $_POST['pages'] ?? [];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    // Exclude superadmins from blocking
    if ($target && !empty($pages) && $title && $message) {
        // Check if the target is a superadmin
        $stmt = $db->prepare("SELECT role FROM users WHERE account_number = :target");
        $stmt->execute(['target' => $target]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['role'] === 'superadmin') {
            echo "<p style='color:red'>You cannot block a superadmin.</p>";
        } else {
            // Insert targeted block
            $stmt = $db->prepare("INSERT INTO page_blocks (page_name, title, message, account_number) VALUES (:page_name, :title, :message, :account_number)");

            foreach ($pages as $page) {
                $stmt->execute([
                    'page_name' => $page,
                    'title' => $title,
                    'message' => $message,
                    'account_number' => $target
                ]);
            }

            // Redirect after block is applied
            header("Location: " . $_SERVER['PHP_SELF']);
            exit; // Make sure to stop further execution after the redirect
        }
    } else {
        echo "<p style='color:red'>All fields must be filled out.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Targeted Block (Superadmin)</title>
</head>
<body>
<?php include('navigation.php'); ?>

<h1>🔧 Targeted Block (Superadmin)</h1>

<!-- Search for a user or admin -->
<form method="GET">
    <input type="text" name="query" placeholder="Search by username or number" required>
    <button type="submit" name="search">Search</button>
</form>

<?php if (!empty($searchResults)): ?>
    <form method="POST">
        <h3>👤 Targeted User:</h3>
        <?php foreach ($searchResults as $user): ?>
            <label>
                <input type="radio" name="target" value="<?= $user['account_number'] ?>" required>
                <?= htmlspecialchars($user['pseudo']) ?> (<?= isset($user['role']) ? htmlspecialchars($user['role']) : 'Not specified' ?> - <?= $user['account_number'] ?>)
            </label><br>
        <?php endforeach; ?>

        <h3>📄 Select pages to block:</h3>
        <?php foreach ($pagesList as $filename => $label): ?>
            <label><input type="checkbox" name="pages[]" value="<?= $filename ?>"> <?= $label ?></label><br>
        <?php endforeach; ?>

        <h3>📝 Block message:</h3>
        <input type="text" name="title" placeholder="Message title" required><br><br>
        <textarea name="message" rows="4" cols="50" placeholder="Message content" required></textarea><br><br>

        <button type="submit" name="start_block">🚫 Start Block</button>
    </form>
<?php else: ?>
    <p>No results for this search.</p>
<?php endif; ?>

</body>
</html>
