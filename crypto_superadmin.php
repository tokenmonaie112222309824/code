<?php
session_start();
include('../config/database.php');
include($_SERVER['DOCUMENT_ROOT'] . '/web/public/check_block.php'); 

// Check if the user is a superadmin
if (!isset($_SESSION['account_number']) || $_SESSION['role'] !== 'superadmin') {
    echo "Access denied.";
    exit;
}

// Check superadmin password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the submitted password
    $submitted_password = $_POST['password'] ?? '';
    
    // Fetch the superadmin password from the database
    $stmt = $db->query("SELECT * FROM superadmin_passwords ORDER BY id DESC LIMIT 1");
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($superadmin && password_verify($submitted_password, $superadmin['password_hash'])) {
        // If the password is correct, continue access to the page
        $_SESSION['superadmin_authenticated'] = true;
    } else {
        // If the password is incorrect
        echo "Incorrect password. Access denied.";
        exit;
    }
}

// If the user is authenticated, display the page content
if (isset($_SESSION['superadmin_authenticated']) && $_SESSION['superadmin_authenticated']) {
    // Processing the form to add addresses
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addresses'])) {
        $cryptoType = $_POST['crypto_type'];
        $addresses = $_POST['addresses']; // List of addresses, separated by line breaks

        // Split the addresses by line
        $addressesArray = explode("\n", $addresses);

        // Clean and insert into the database
        foreach ($addressesArray as $address) {
            $address = trim($address); // Remove unnecessary spaces around the address
            if (!empty($address)) {
                // Check if the address already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM crypto_addresses WHERE address = :address");
                $stmt->execute(['address' => $address]);
                $count = $stmt->fetchColumn();

                // If the address does not already exist, add it
                if ($count == 0) {
                    $stmt = $db->prepare("INSERT INTO crypto_addresses (crypto_type, address, date) VALUES (:crypto_type, :address, NOW())");
                    $stmt->execute(['crypto_type' => $cryptoType, 'address' => $address]);
                }
            }
        }

        echo "The addresses have been successfully added.";
    }
} else {
    // If the user is not authenticated, display the password form
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
    <title>Add Crypto Addresses - Superadmin</title>
</head>
<body>
<h1>Add Crypto Addresses</h1>
<?php include('navigation.php'); ?>
<form method="POST" action="">
    <label for="crypto_type">Select Cryptocurrency:</label>
    <select name="crypto_type" id="crypto_type" required>
        <option value="XMR">XMR</option>
        <option value="ETH">ETH</option>
        <option value="BTC">BTC</option>
        <!-- Add other cryptos if necessary -->
    </select>
    <br><br>

    <label for="addresses">Enter Addresses (separate by line breaks):</label>
    <textarea name="addresses" id="addresses" rows="6" cols="40" required></textarea>
    <br><br>

    <button type="submit">Add Addresses</button>
</form>

</body>
</html>
