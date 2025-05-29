<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin');
$is_superadmin = isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
/* Style global de la page */
body {
    background-color: #333333;
    color: #fff;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

/* Conteneur principal du menu */
nav {
    padding: 20px;
}

/* Liste du menu */
ul {
    display: flex;
    flex-wrap: wrap; /* Permet les retours à la ligne */
    list-style-type: none;
    padding: 0;
    margin: 0;
    justify-content: center; /* Centrage horizontal */
    transition: all 0.3s ease;
}

/* Élément de menu */
li {
    margin: 8px;
    flex: 1 1 20%; /* 5 boutons par ligne max */
    text-align: center;
}

/* Lien de menu */
a {
    display: block;
    background-color: #9b59b6;
    color: #fff;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
    text-align: center;
    transition: background-color 0.3s, transform 0.2s;
}

/* Effet hover */
a:hover {
    background-color: #8e44ad;
    transform: scale(1.05);
}

/* Responsive : tablette */
@media (max-width: 768px) {
    li {
        flex: 1 1 30%; /* 3 par ligne */
    }
}

/* Responsive : mobile */
@media (max-width: 480px) {
    li {
        flex: 1 1 100%; /* 1 par ligne */
    }
}

    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="wallet.php">💰 My Wallet</a></li>
            <li><a href="valeur.php">📉 Value</a></li>
            <li><a href="buy_sell.php">💸 Buy/Sell</a></li>
            <li><a href="contact.php">💬 Chat</a></li>
            <li><a href="profil.php">📢 Profile</a></li>
        </ul>

        <?php if ($is_admin): ?>
            <ul>
                <li><a href="searchuser.php">🔍 User Management</a></li>
                <li><a href="purchase.php">💸 Manage Orders</a></li>
                <li><a href="admin_forum.php">💸 Manage Forum</a></li>
                <li><a href="adminlitige.php">⚖️ Manage Disputes</a></li>
                <li><a href="admin_discussion.php">👥 Team</a></li>
            </ul>
        <?php endif; ?>

        <?php if ($is_superadmin): ?>
            <ul>
                <li><a href="parametres_systeme.php">⚙️ System Settings</a></li>
                <li><a href="gestion_roles.php">🔐 Role Management</a></li>
                <li><a href="admin_reserves.php">📦 Crypto Reserves</a></li>
                <li><a href="transaction_superadmin.php">Transactions</a></li>
                <li><a href="withdraw_superadmin.php">Withdrawal</a></li>
                <li><a href="gestion_superadmin.php">Admin Account Management</a></li>
                <li><a href="user_superadmin.php">User Account Management</a></li>
                <li><a href="crypto_superadmin.php">Crypto Address Management</a></li>
                <li><a href="pass_superadmin.php">Superadmin Password</a></li>
                <li><a href="fees_superadmin.php">Fees Management</a></li>
            </ul>
        <?php endif; ?>
    </nav>
</body>
</html>
