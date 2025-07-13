<?php
$servername = "localhost";
$username = "u953085669_store";
$password = "@Store2000";
$database = "u953085669_store";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper Unicode (including Khmer) support
$conn->set_charset("utf8mb4");
?>
