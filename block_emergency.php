<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Superadmin check
if (!isset($_SESSION['account_number']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "Access denied.";
    exit;
}

// Updated list of blockable pages
$pagesList = [
    'admin_discussion.php' => '👥 Internal Admin Chat',
    'admin_forum.php' => '🛠️ Admin Forum',
    'admin_reserves.php' => '📦 Crypto Reserves',
    'adminlitige.php' => '⚖️ Manage Disputes',
    'adminlitige_discussion.php' => '🗨️ Dispute Discussion',
    'buy.php' => '💸 Buy Currency',
    'buy_sell.php' => '💸 Buy/Sell',
    'confirm_account.php' => '✅ Account Confirmation',
    'ban.php' => '🔒 Ban a User',
    'contact.php' => '💬 Chat',
    'discussion.php' => '🗣️ User Discussion',
    'forum.php' => '💬 Public Forum',
    'gestion_roles.php' => '🛡️ Role Management',
    'litige.php' => '⚠️ Open a Dispute',
    'login.php' => '🔑 Login',
    'parametres_systeme.php' => '🔧 System Settings',
    'profil.php' => '📢 Profile',
    'purchase.php' => '💰 Manage Orders',
    'receive.php' => '📥 Receiving',
    'register.php' => '📝 Register',
    'searchuser.php' => '🔍 User Management',
    'sell.php' => '💰 Sell Currency',
    'transaction_superadmin.php' => '📊 Transactions (Superadmin)',
    'transfer.php' => '🔁 Transfer',
    'valeur.php' => '📉 Currency Value',
    'wallet.php' => '💼 Wallet'
];

// Emergency block form submission
if (isset($_POST['emergency_block'])) {
    $pages = $_POST['emergency_pages'] ?? [];
    $title = trim($_POST['emergency_title']) ?: "🚨 Emergency Block";
    $message = trim($_POST['emergency_message']) ?: "This page is currently blocked for all users and admins.";

    $target_users = isset($_POST['block_all_users']) ? 1 : 0;
    $target_admins = isset($_POST['block_all_admins']) ? 1 : 0;
    $block_ultimate = isset($_POST['block_ultimate']) ? 1 : 0;

    if (!empty($pages)) {
        $stmt = $db->prepare("INSERT INTO page_blocks (page_name, title, message, target_all_users, target_all_admins, block_ultimate) VALUES (:page_name, :title, :message, :target_all_users, :target_all_admins, :block_ultimate)");

        foreach ($pages as $page) {
            $stmt->execute([
                'page_name' => $page,
                'title' => $title,
                'message' => $message,
                'target_all_users' => $target_users,
                'target_all_admins' => $target_admins,
                'block_ultimate' => $block_ultimate
            ]);
        }

        // Redirection après soumission pour éviter une soumission répétée
        header("Location: " . $_SERVER['PHP_SELF']);
        exit; // Assure que le script s'arrête après la redirection
    } else {
        echo "<p style='color:red'>Please select at least one page to block.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency Block (Superadmin)</title>
</head>
<body>
<?php include('navigation.php'); ?>

<h1>🚨 Emergency Block (Superadmin)</h1>

<!-- Emergency block form -->
<form method="POST">
    <h3>📄 Select pages to block:</h3>
    <?php foreach ($pagesList as $filename => $label): ?>
        <label><input type="checkbox" name="emergency_pages[]" value="<?= $filename ?>"> <?= $label ?></label><br>
    <?php endforeach; ?>

    <h3>📝 Emergency block message (optional):</h3>
    <input type="text" name="emergency_title" placeholder="Message title"><br><br>
    <textarea name="emergency_message" rows="4" cols="50" placeholder="Message content"></textarea><br><br>

    <h3>🎯 Select block type:</h3>
    <label><input type="checkbox" name="block_all_users"> 🔒 Block all users</label><br>
    <label><input type="checkbox" name="block_all_admins"> 🔒 Block all admins</label><br>
    <label><input type="checkbox" name="block_ultimate"> 🚫 Ultimate block (all visitors)</label><br><br>

    <button type="submit" name="emergency_block">🚨 Block Now (Users and Admins)</button>
</form>

</body>
</html>
