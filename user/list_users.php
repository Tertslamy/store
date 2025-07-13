<?php
include 'db.php';
$result = $conn->query("SELECT u.ID, u.FirstName, u.LastName, g.Name as Gender 
    FROM `User` u LEFT JOIN Gender g ON u.GenderID = g.ID");

echo "<h2>User List</h2><ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>{$row['ID']} - {$row['FirstName']} {$row['LastName']} - {$row['Gender']}</li>";
}
echo "</ul>";
?>
