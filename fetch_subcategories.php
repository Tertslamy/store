<?php
// fetch_subcategories.php
// This script provides a list of subcategories based on a parent category ID.
// It is called via AJAX from the header.

header('Content-Type: application/json'); // Set the content type to JSON
include 'db.php'; // Include your database connection

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$subCategories = [];

if ($categoryId > 0) {
    // Prepare a secure query to prevent SQL injection
    $stmt = $conn->prepare("SELECT id AS ID, name AS Name FROM sub_categories WHERE category_id = ? ORDER BY name ASC");

    if ($stmt) {
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch all results into an array
        while ($row = $result->fetch_assoc()) {
            $subCategories[] = $row;
        }

        $stmt->close();
    }
}

// Return the subcategories as a JSON response
echo json_encode($subCategories);

// Close the database connection
$conn->close();
?>