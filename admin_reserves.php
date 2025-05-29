<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is authenticated as admin (superadmin role)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo "<p>You are not authorized to access this page.</p>";
    exit;
}

// If superadmin is authenticated, show reserves
if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {

    // Retrieve crypto reserves
    $stmt = $db->prepare("SELECT * FROM crypto_reserves");
    $stmt->execute();
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process reserve updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reserve'])) {
        $cryptoName = $_POST['crypto_name'];
        $newAmount = $_POST['new_amount'];
        $superadminPassword = $_POST['superadmin_password'];

        // Check the password to modify reserves
        $stmt = $db->prepare("SELECT * FROM superadmin_passwords LIMIT 1");
        $stmt->execute();
        $superadminPasswordHash = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($superadminPasswordHash && password_verify($superadminPassword, $superadminPasswordHash['password_hash'])) {
            // Update crypto reserve
            $updateStmt = $db->prepare("UPDATE crypto_reserves SET reserve_amount = :new_amount WHERE crypto_name = :crypto_name");
            $updateStmt->execute([
                'new_amount' => $newAmount,
                'crypto_name' => $cryptoName
            ]);
            $_SESSION['message'] = "Reserve successfully updated.";
        } else {
            $_SESSION['message'] = "Incorrect password to modify reserves.";
        }
    }

   
    // Display reserves
    echo "<h1>Crypto Reserves</h1>";
    if (isset($_SESSION['message'])) {
        echo "<p style='color: green;'>{$_SESSION['message']}</p>";
        unset($_SESSION['message']);
    }
    include('navigation.php');

    echo "<table border='1'>";
    echo "<thead><tr><th>Crypto Name</th><th>Reserve</th><th>Action</th></tr></thead>";
    echo "<tbody>";
    foreach ($reserves as $reserve) {
        echo "<tr>";
        echo "<td>{$reserve['crypto_name']}</td>";
        echo "<td>{$reserve['reserve_amount']}</td>";
        echo "<td>
                <form method='POST'>
                    <input type='hidden' name='crypto_name' value='{$reserve['crypto_name']}'>
                    <input type='number' name='new_amount' value='{$reserve['reserve_amount']}' min='0' step='any' required>
                    <input type='password' name='superadmin_password' placeholder='Superadmin password' required>
                    <button type='submit' name='update_reserve'>Update</button>
                </form>
              </td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
} else {
    echo "<p>Unauthorized access.</p>";
}
?>
