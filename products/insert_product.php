<?php
// insert_product.php
// Handles the submission of a new product form.

include 'db.php';

// Create the uploads directory if it doesn't exist, with full permissions
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start a database transaction for atomicity.
        // If any step fails, we can rollback all changes.
        $conn->begin_transaction();

        // --- 1. Sanitize and get general form data ---
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        $price = floatval($_POST['price']);
        $discount = floatval($_POST['discount']);
        $condition = $_POST['condition'];
        $free_shipping = (int)$_POST['free_shipping'];
        // Use ternary operators to handle optional fields, setting them to null if empty
        $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $commune_id = !empty($_POST['commune_id']) ? (int)$_POST['commune_id'] : null;
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $map_embed = trim($_POST['map_embed']);
        $phone = trim($_POST['phone']);
        $telegram = trim($_POST['telegram']);
        $facebook = trim($_POST['facebook']);

        // Basic validation for required fields
        if (empty($name) || $category_id === 0 || empty($condition)) {
            throw new Exception("មានបញ្ហាទិន្នន័យ: សូមបំពេញគ្រប់ចំណុចចាំបាច់។");
        }

        // --- 2. Insert the product into the `products` table ---
        $stmt = $conn->prepare("INSERT INTO products (name, description, category_id, price, discount, `condition`, free_shipping, province_id, district_id, commune_id, village_id, map_embed, phone, telegram, facebook, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare product insert statement: " . $conn->error);
        }

        $stmt->bind_param("ssiddsiiiiissss",
            $name, $description, $category_id, $price, $discount, $condition, $free_shipping,
            $province_id, $district_id, $commune_id, $village_id, $map_embed, $phone, $telegram, $facebook
        );

        if (!$stmt->execute()) {
            throw new Exception("មានបញ្ហាក្នុងការរក្សាទុកផលិតផល: " . $stmt->error);
        }

        $product_id = $conn->insert_id;
        $stmt->close();

        // --- 3. Handle image uploads and insert into the `images` table ---
        if (!empty($_FILES['images']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                // Check for a successful upload and if the file exists in the temp directory
                if ($_FILES['images']['error'][$key] == 0 && is_uploaded_file($tmp_name)) {
                    $file_name = $_FILES['images']['name'][$key];
                    $file_size = $_FILES['images']['size'][$key];
                    $file_type = $_FILES['images']['type'][$key];

                    // Validate file type
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("ប្រភេទឯកសារមិនត្រឹមត្រូវសម្រាប់: " . $file_name);
                    }

                    // Validate file size
                    if ($file_size > $max_size) {
                        throw new Exception("ឯកសារធំពេកសម្រាប់: " . $file_name);
                    }

                    // Generate a unique and safe filename to prevent overwrites
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_filename = 'product_' . $product_id . '_' . $key . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $uploadDir . $new_filename;

                    // Move the uploaded file to its permanent location
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        // Insert image record into the database
                        $image_stmt = $conn->prepare("INSERT INTO images (product_id, image_path, alt_text, created_at) VALUES (?, ?, ?, NOW())");
                        
                        if (!$image_stmt) {
                            throw new Exception("Failed to prepare image insert statement: " . $conn->error);
                        }

                        $alt_text = $name . ' - រូបភាពទី ' . ($key + 1);
                        $image_stmt->bind_param("iss", $product_id, $upload_path, $alt_text);
                        
                        if (!$image_stmt->execute()) {
                            throw new Exception("មានបញ្ហាក្នុងការរក្សាទុករូបភាព: " . $image_stmt->error);
                        }
                        $image_stmt->close();
                    } else {
                        throw new Exception("មិនអាចបញ្ចូលរូបភាព: " . $file_name);
                    }
                }
            }
        }

        // --- 4. Handle dynamic attributes and insert into `product_attributes` table ---
        $attr_stmt = $conn->prepare("INSERT INTO product_attributes (product_id, attribute_id, value) VALUES (?, ?, ?)");
        if (!$attr_stmt) {
            throw new Exception("Failed to prepare attribute insert statement: " . $conn->error);
        }

        foreach ($_POST as $key => $value) {
            // Check if the field is a dynamic attribute by its name format
            if (strpos($key, 'attribute_') === 0) {
                $attribute_id = str_replace('attribute_', '', $key);
                if (!empty($value)) {
                    $attr_stmt->bind_param("iis", $product_id, $attribute_id, $value);
                    if (!$attr_stmt->execute()) {
                        // Log the error but don't stop the transaction for a non-critical field
                        error_log("Failed to insert attribute (ID: $attribute_id) for product (ID: $product_id): " . $attr_stmt->error);
                    }
                }
            }
        }
        $attr_stmt->close();

        // All steps completed successfully, commit the transaction
        $conn->commit();

        // Redirect with a success message
        echo "<script>
            alert('✅ ផលិតផលត្រូវបានបញ្ចូលដោយជោគជ័យ!');
            window.location.href = 'add_product.php';
        </script>";

    } catch (Exception $e) {
        // Rollback the transaction on any error
        $conn->rollback();
        
        // Log the full error to the server's error log
        error_log("Product insertion failed: " . $e->getMessage());

        // Display a user-friendly error message
        echo "<script>
            alert('❌ មានបញ្ហា: " . addslashes($e->getMessage()) . "');
            window.history.back();
        </script>";
    }
} else {
    // If someone tries to access this script directly, redirect them
    header("Location: add_product.php");
    exit();
}
?>