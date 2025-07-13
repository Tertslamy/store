<?php
// get_attributes.php
include 'db.php';

$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

if ($category_id > 0) {
    try {
        // Get attributes for the category
        $stmt = $conn->prepare("SELECT id, name, input_type, unit FROM attributes WHERE category_id = ? ORDER BY name");
        if ($stmt) {
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $html = '';
            while ($row = $result->fetch_assoc()) {
                $html .= '<div class="form-group">';
                $html .= '<label>' . htmlspecialchars($row['name']) . '</label>';
                
                if ($row['input_type'] === 'select') {
                    // Get options for this attribute
                    $options_stmt = $conn->prepare("SELECT value FROM attribute_options WHERE attribute_id = ? ORDER BY value");
                    if ($options_stmt) {
                        $options_stmt->bind_param("i", $row['id']);
                        $options_stmt->execute();
                        $options_result = $options_stmt->get_result();
                        
                        $html .= '<select name="attr_' . $row['id'] . '" class="form-control">';
                        $html .= '<option value="">ជ្រើសរើស</option>';
                        
                        while ($option_row = $options_result->fetch_assoc()) {
                            $html .= '<option value="' . htmlspecialchars($option_row['value']) . '">' . htmlspecialchars($option_row['value']) . '</option>';
                        }
                        $html .= '</select>';
                        $options_stmt->close();
                    }
                } elseif ($row['input_type'] === 'textarea') {
                    $html .= '<textarea name="attr_' . $row['id'] . '" class="form-control" rows="3"';
                    if ($row['unit']) {
                        $html .= ' placeholder="' . htmlspecialchars($row['unit']) . '"';
                    }
                    $html .= '></textarea>';
                } elseif ($row['input_type'] === 'number') {
                    $html .= '<input type="number" name="attr_' . $row['id'] . '" class="form-control"';
                    if ($row['unit']) {
                        $html .= ' placeholder="' . htmlspecialchars($row['unit']) . '"';
                    }
                    $html .= '>';
                } else {
                    // Default to text input
                    $html .= '<input type="text" name="attr_' . $row['id'] . '" class="form-control"';
                    if ($row['unit']) {
                        $html .= ' placeholder="' . htmlspecialchars($row['unit']) . '"';
                    }
                    $html .= '>';
                }
                
                $html .= '</div>';
            }
            
            echo $html;
            $stmt->close();
        } else {
            error_log("Failed to prepare attributes statement: " . $conn->error);
            echo '<div class="alert alert-danger">Error loading attributes.</div>';
        }
    } catch (Exception $e) {
        error_log("Error in get_attributes.php: " . $e->getMessage());
        echo '<div class="alert alert-danger">An error occurred while loading attributes.</div>';
    }
} else {
    echo ''; // Return empty if no valid category_id
}

$conn->close();
?>