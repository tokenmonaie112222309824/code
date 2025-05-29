<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('header.php'); ?>
    <meta http-equiv="refresh" content="6;url=login.php"> <!-- Redirection après 6 secondes -->
    <link rel="stylesheet" href="../assets/css/index.css">
</head>
<body>

    <div class="container">
        <img src="../assets/logo.PNG" alt="Logo">
        <h1>Welcome to VEXIUM</h1>
    </div>

    <div class="taglines">
        <p class="line" style="animation-delay: 0.5s;">Anonymity. Freedom. Control.</p>
        <p class="line" style="animation-delay: 1.5s;">Your pseudonym is your only footprint.</p>
        <p class="line" style="animation-delay: 2.5s;">Buy. Trade. In full discretion.</p>
    </div>

</body>
</html>
