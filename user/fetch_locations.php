<?php
// fetch_locations.php
header('Content-Type: application/json'); // Indicate JSON response
include 'db.php'; // Your mysqli database connection

$type = isset($_GET['type']) ? $_GET['type'] : '';
$parentId = isset($_GET['parentId']) ? (int)$_GET['parentId'] : 0;

$results = [];

try {
    if ($type === 'districts' && $parentId > 0) {
        $stmt = $conn->prepare("SELECT id, name, khmer_name FROM districts WHERE province_id = ? ORDER BY name");
        if ($stmt) {
            $stmt->bind_param("i", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
            $stmt->close();
            if ($result) $result->free();
        } else {
            error_log("Failed to prepare districts statement: " . $conn->error);
            echo json_encode(['error' => 'Database error fetching districts.']);
            exit;
        }
    } elseif ($type === 'communes' && $parentId > 0) {
        // For communes, assuming parentId is district_id
        $stmt = $conn->prepare("SELECT id, name, khmer_name FROM communes WHERE district_id = ? ORDER BY name");
        if ($stmt) {
            $stmt->bind_param("i", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
            $stmt->close();
            if ($result) $result->free();
        } else {
            error_log("Failed to prepare communes statement: " . $conn->error);
            echo json_encode(['error' => 'Database error fetching communes.']);
            exit;
        }
    } elseif ($type === 'villages' && $parentId > 0) { // NEW: Handle villages
        // For villages, assuming parentId is commune_id
        $stmt = $conn->prepare("SELECT id, name, khmer_name FROM villages WHERE commune_id = ? ORDER BY name");
        if ($stmt) {
            $stmt->bind_param("i", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
            $stmt->close();
            if ($result) $result->free();
        } else {
            error_log("Failed to prepare villages statement: " . $conn->error);
            echo json_encode(['error' => 'Database error fetching villages.']);
            exit;
        }
    } else {
        echo json_encode(['error' => 'Invalid request parameters.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error in fetch_locations.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected server error occurred.']);
    exit;
}

echo json_encode($results);

// Close connection (optional, as script ends here)
$conn->close();
?>