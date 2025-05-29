<?php
session_start();

// Check if the user is authenticated as a superadmin
if (!isset($_SESSION['account_number']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo "Access Denied.";
    exit;
}

// Verify the superadmin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['superadmin_authenticated'])) {
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
    // Fetch fees
    include($_SERVER['DOCUMENT_ROOT'] . '/web/config/database.php');
    $stmt = $db->query("SELECT * FROM fees");
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle fee updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fees'])) {
        $fee_type = $_POST['fee_type'];
        $fee_percent = $_POST['fee_percent'];
        
        // Validate the fee percent
        if (!is_numeric($fee_percent) || $fee_percent < 0 || $fee_percent > 100) {
            echo "<div class='error'>Invalid fee percentage.</div>";
        } else {
            // Update the fee in the database
            $updateStmt = $db->prepare("UPDATE fees SET fee_percent = :fee_percent WHERE fee_type = :fee_type");
            $updateStmt->execute([
                'fee_percent' => $fee_percent,
                'fee_type' => $fee_type
            ]);

            echo "<div style='color:green;'>Fees updated successfully.</div>";
        }
    }
} else {
    // If the user is not authenticated, show the password form
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
    <title>Manage Fees</title>
</head>
<body>

    <h1>Manage Fees (Superadmin)</h1>
    <?php include('navigation.php'); ?>
    <!-- Display fees -->
    <h3>Current Fees</h3>
    <table>
        <thead>
            <tr>
                <th>Fee Type</th>
                <th>Fee Percentage</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fees as $fee): ?>
                <tr>
                    <td><?= htmlspecialchars($fee['fee_type']) ?></td>
                    <td><?= htmlspecialchars($fee['fee_percent']) ?>%</td>
                    <td>
                        <!-- Form to update the fee -->
                        <form method="POST" action="">
                            <input type="hidden" name="fee_type" value="<?= htmlspecialchars($fee['fee_type']) ?>">
                            <input type="number" name="fee_percent" value="<?= htmlspecialchars($fee['fee_percent']) ?>" required min="0" max="100">
                            <button type="submit" name="update_fees">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
