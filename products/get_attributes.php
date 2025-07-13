<?php
include 'db.php';

if (isset($_POST['category_id'])) {
    $category_id = $_POST['category_id'];

    // First, get the category name to use for conditional logic
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category_name = $result->fetch_assoc()['name'];
    $stmt->close();
    
    echo "<h4>លក្ខណៈពិសេស:</h4>";

    // Use a switch statement to handle different categories
    switch ($category_name) {
        case 'Smartphone':
            $attr_names = ['Brand', 'Model', 'RAM', 'Storage'];
            break;
        case 'Car':
            $attr_names = ['Brand', 'Model', 'Year', 'Engine Size'];
            break;
        default:
            $attr_names = null;
            break;
    }

    if ($attr_names) {
        // Fetch specific attributes by name for predefined categories
        $placeholders = implode(',', array_fill(0, count($attr_names), '?'));
        $stmt = $conn->prepare("SELECT id, name, unit, input_type FROM attributes WHERE category_id = ? AND name IN ($placeholders) ORDER BY FIELD(name, $placeholders)");
        
        $params = array_merge([$category_id], $attr_names, $attr_names);
        $types = str_repeat('s', count($params)); // 'i' for category_id, 's' for names
        array_unshift($params, 'i' . str_repeat('s', count($attr_names) * 2));
        call_user_func_array([$stmt, 'bind_param'], $params);
        $stmt->execute();
        $result = $stmt->get_result();

    } else {
        // For all other categories, fetch all attributes dynamically
        $stmt = $conn->prepare("SELECT id, name, unit, input_type FROM attributes WHERE category_id = ? ORDER BY name");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    // Now, generate the HTML for the inputs
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="attribute-group">';
            echo "<label>{$row['name']}" . (!empty($row['unit']) ? " ({$row['unit']})" : "") . ":</label>";
            
            switch ($row['input_type']) {
                case 'number':
                    echo "<input type='number' name='attributes[{$row['id']}]' step='any'>";
                    break;
                case 'text':
                default: // Fallback to text input for any other type
                    echo "<input type='text' name='attributes[{$row['id']}]'>";
                    break;
            }
            echo '</div>';
        }
    } else {
        echo "<p>មិនមានលក្ខណៈពិសេសសម្រាប់ប្រភេទនេះទេ។</p>";
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    $conn->close();
}
?>