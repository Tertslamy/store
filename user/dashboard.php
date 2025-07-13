<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to the Dashboard</h2>
    <p>Hello, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>!</p>
    <p>You are logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>

    <ul>
        <li><a href="list_users.php">Manage Users</a></li>
        <li><a href="list_gender.php">Manage Genders</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</body>
</html>
