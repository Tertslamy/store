<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // Plaintext password

    $stmt = $conn->prepare("SELECT ID, UserName, `Password`, FirstName, LastName FROM `User` WHERE UserName = ? AND IsActive = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // ⚠️ Use plain comparison if password is stored in plain text (not recommended)
        if ($password === $user['Password']) {
            $_SESSION['user_id'] = $user['ID'];
            $_SESSION['username'] = $user['UserName'];
            $_SESSION['name'] = $user['FirstName'] . ' ' . $user['LastName'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "❌ Invalid password.";
        }
    } else {
        $error = "❌ User not found or inactive.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<h2>Login</h2>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="POST">
    <label>Username:</label><br>
    <input type="text" name="username" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" id="password" required>
    <button type="button" onclick="togglePassword()">Show</button><br><br>

    <button type="submit">Login</button>
</form>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const btn = event.target;
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        btn.textContent = 'Hide';
    } else {
        passwordInput.type = 'password';
        btn.textContent = 'Show';
    }
}
</script>
</body>
</html>
