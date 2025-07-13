<?php
include 'db.php';
$genders = $conn->query("SELECT * FROM Gender");
?>

<form method="POST">
    <input type="text" name="FirstName" placeholder="First Name" required>
    <input type="text" name="LastName" placeholder="Last Name" required>
    <select name="GenderID">
        <option value="">-- Select Gender --</option>
        <?php while ($g = $genders->fetch_assoc()): ?>
            <option value="<?= $g['ID'] ?>"><?= $g['Name'] ?></option>
        <?php endwhile; ?>
    </select>
    <input type="text" name="PhoneNumber" placeholder="Phone Number" required>
    <input type="text" name="UserName" placeholder="Username">
    <input type="password" name="Password" placeholder="Password" required>
    <input type="submit" name="submit" value="Add User">
</form>

<?php
if (isset($_POST['submit'])) {
    $stmt = $conn->prepare("INSERT INTO `User` 
        (FirstName, LastName, GenderID, PhoneNumber, UserName, `Password`, IsActive)
        VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssisss",
        $_POST['FirstName'],
        $_POST['LastName'],
        $_POST['GenderID'],
        $_POST['PhoneNumber'],
        $_POST['UserName'],
        $_POST['Password'] // Encrypt before saving in production
    );

    if ($stmt->execute()) {
        echo "User added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
