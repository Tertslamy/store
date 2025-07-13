<?php
// fetch_brands.php
// This script fetches brands based on a given ProductSubCategoryID.
// It is designed to be called via AJAX from the frontend.

// Include mysqli database connection
include "db.php"; // Ensure this path is correct

header('Content-Type: application/json'); // Set header to indicate JSON response

$response = [];

// Check if product_sub_category_id is provided in the GET request
if (isset($_GET['product_sub_category_id']) && is_numeric($_GET['product_sub_category_id'])) {
    $productSubCategoryId = (int)$_GET['product_sub_category_id'];

    // Prepare and execute the statement to fetch brands
    // Filters by ProductSubCategoryID and ensures brand is active
    $stmt = $conn->prepare("SELECT ID, Name FROM Brand WHERE ProductSubCategoryID = ? AND IsActive = TRUE ORDER BY Name ASC");
    
    if ($stmt) {
        $stmt->bind_param("i", $productSubCategoryId);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch all brands into an array
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
        $stmt->close();
    } else {
        // Log error if statement preparation fails
        error_log("fetch_brands.php: Failed to prepare statement: " . $conn->error);
        // In a production environment, you might return an error message here:
        // $response = ['error' => 'Database query preparation failed.'];
    }
} else {
    // If product_sub_category_id is not provided or invalid
    // $response = ['error' => 'Invalid or missing product_sub_category_id.'];
}

// Close the database connection
$conn->close();

// Encode the response array as JSON and output it
echo json_encode($response);
?>
