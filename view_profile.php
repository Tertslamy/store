<?php
session_start();
require_once 'db.php'; // Use same $pdo

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT FirstName, LastName, PhoneNumber FROM User WHERE ID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
</head>
<body>
    <h2>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</h2>
    <p><strong>First Name:</strong> <?= htmlspecialchars($user['FirstName']) ?></p>
    <p><strong>Last Name:</strong> <?= htmlspecialchars($user['LastName']) ?></p>
    <p><strong>Phone Number:</strong> <?= htmlspecialchars($user['PhoneNumber']) ?></p>

    <a href="logout.php">Logout</a>
</body>
</html>
