<?php
// Start the session only if it’s not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');

// Vérification si l'utilisateur est superadmin
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';

// The rest of your blocking verification code
$currentPage = basename($_SERVER['PHP_SELF']);

// Check for block on this page
$stmt = $db->prepare("
    SELECT title, message
    FROM page_blocks
    WHERE page_name = :page
    AND (target_all_users = 1 OR block_ultimate = 1)
    ORDER BY blocked_at DESC
    LIMIT 1
");

$stmt->execute(['page' => $currentPage]);
$block = $stmt->fetch(PDO::FETCH_ASSOC);

// If the page is blocked, display the message and stop execution (except for superadmins)
if ($block && !$isSuperAdmin) {
    echo "<h2 style='color:red'>" . htmlspecialchars($block['title']) . "</h2>";
    echo "<p>" . nl2br(htmlspecialchars($block['message'])) . "</p>";
    exit;
}
?>
