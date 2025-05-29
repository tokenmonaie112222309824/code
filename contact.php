<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Include the database configuration
include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');

// Check if the user is logged in
if (!isset($_SESSION['account_number'])) {
    echo "You must be logged in to use this feature.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <link rel="stylesheet" href="../assets/css/contac.css">
</head>
<body>
    <div class="container">
        <h1>Terms of Use</h1>
        <?php include('navigation.php'); ?>
        
        <p>Welcome to our communication platform. Before you start using the chat features, please read and accept the following terms:</p>
        
        <div class="terms-container">
    <ul class="terms-list">
        <li>Respect for other users is paramount.</li>
        <li>Defamatory, racist, or violent comments are prohibited.</li>
        <li>Any abuse or inappropriate use of the services may result in penalties, including account suspension.</li>
        <li>Disputes will be managed by the administrators and may require additional information.</li>
        <li>You agree not to share sensitive or personal information on this platform.</li>
    </ul>
</div>


        <p style="text-align: center;">If you agree with these terms, you can continue to use the following features:</p>


        <!-- Actions que l'utilisateur peut effectuer -->
        <div class="terms-actions">
            <a href="forum.php" class="btn">Access the Forum</a><br><br>
            <a href="litige.php" class="btn">Open a Dispute</a><br><br>
            <a href="terms_of_use.html" class="btn" target="_blank">Read Full Terms of Use</a>

        </div>
    </div>
</body>
</html>


