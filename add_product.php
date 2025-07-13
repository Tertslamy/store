<?php
// add_product.php
// This page allows users to add new products and displays existing products.

// Include necessary files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include "db.php"; // Your database connection
include "header.php"; // Your header with user session and dynamic category fetching

// Define upload directory for product images
$uploadDir = 'uploads/product_images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist, with write permissions
}

$message = ''; // To store success or error messages

// --- PHP Logic for Handling Form Submission (Add Product) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Basic validation and sanitization
    $name = $conn->real_escape_string($_POST['name']);
    $productSubCategoryID = (int)$_POST['productSubCategoryID'];
    $productBrandID = !empty($_POST['productBrandID']) ? (int)$_POST['productBrandID'] : NULL;
    $taxTypeID = !empty($_POST['taxTypeID']) ? (int)$_POST['taxTypeID'] : NULL;
    $conditionID = !empty($_POST['conditionID']) ? (int)$_POST['conditionID'] : NULL;
    $colorID = !empty($_POST['colorID']) ? (int)$_POST['colorID'] : NULL;
    $transmissionID = !empty($_POST['transmissionID']) ? (int)$_POST['transmissionID'] : NULL;
    $engineTypeID = !empty($_POST['engineTypeID']) ? (int)$_POST['engineTypeID'] : NULL;
    $bodyTypeID = !empty($_POST['bodyTypeID']) ? (int)$_POST['bodyTypeID'] : NULL;
    $vgaID = !empty($_POST['vgaID']) ? (int)$_POST['vgaID'] : NULL;
    $cpuID = !empty($_POST['cpuID']) ? (int)$_POST['cpuID'] : NULL;
    $ramID = !empty($_POST['ramID']) ? (int)$_POST['ramID'] : NULL;
    $storageID = !empty($_POST['storageID']) ? (int)$_POST['storageID'] : NULL;
    $screenID = !empty($_POST['screenID']) ? (int)$_POST['screenID'] : NULL;
    $price = (float)$_POST['price'];
    $discount = !empty($_POST['discount']) ? (float)$_POST['discount'] : NULL;
    $isFreeDelivery = isset($_POST['isFreeDelivery']) ? 1 : 0;
    $description = $conn->real_escape_string($_POST['description']);
    $cityID = (int)$_POST['cityID']; // Assuming these are required and exist
    $districtID = (int)$_POST['districtID'];
    $communeID = (int)$_POST['communeID'];
    $address = $conn->real_escape_string($_POST['address']);
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : NULL;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : NULL;
    $createdDate = date('Y-m-d H:i:s'); // Current timestamp
    $isActive = 1; // Default to active

    // Start a transaction for atomicity
    $conn->begin_transaction();
    $productInserted = false;

    try {
        // Prepare and execute the INSERT statement for the Product table
        $stmt_product = $conn->prepare("INSERT INTO `Product` (Name, ProductSubCategoryID, ProductBrandID, TaxTypeID, ConditionID, ColorID, TransmissionID, EngineTypeID, BodyTypeID, VgaID, CpuID, RamID, StorageID, ScreenID, Price, Discount, IsFreeDilivery, Description, CityID, DistrictID, CommuneID, Address, Latitude, Longitude, CreatedDate, IsActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt_product) {
            $stmt_product->bind_param("siiiiiiiiiiiiiddsisiiiddssi",
                $name, $productSubCategoryID, $productBrandID, $taxTypeID, $conditionID, $colorID,
                $transmissionID, $engineTypeID, $bodyTypeID, $vgaID, $cpuID, $ramID, $storageID,
                $screenID, $price, $discount, $isFreeDelivery, $description, $cityID, $districtID,
                $communeID, $address, $latitude, $longitude, $createdDate, $isActive
            );
            
            if ($stmt_product->execute()) {
                $newProductId = $conn->insert_id; // Get the ID of the newly inserted product
                $productInserted = true;

                // Handle image uploads
                if (!empty($_FILES['product_images']['name'][0])) {
                    $imageCount = count($_FILES['product_images']['name']);
                    $stmt_image = $conn->prepare("INSERT INTO `ProductImage` (Photo, SortOrder, ProductID) VALUES (?, ?, ?)");
                    
                    if ($stmt_image) {
                        for ($i = 0; $i < $imageCount; $i++) {
                            $fileName = basename($_FILES['product_images']['name'][$i]);
                            $targetFilePath = $uploadDir . uniqid() . '_' . $fileName; // Unique filename
                            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                            // Allow certain file formats
                            $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
                            if (in_array($fileType, $allowTypes)) {
                                if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $targetFilePath)) {
                                    $photoPath = $conn->real_escape_string($targetFilePath);
                                    $sortOrder = $i + 1; // Simple sort order
                                    $stmt_image->bind_param("sii", $photoPath, $sortOrder, $newProductId);
                                    $stmt_image->execute();
                                } else {
                                    error_log("Failed to move uploaded file: " . $_FILES['product_images']['tmp_name'][$i]);
                                }
                            } else {
                                error_log("Invalid file type for image: " . $fileType);
                            }
                        }
                        $stmt_image->close();
                    } else {
                        error_log("Failed to prepare statement for ProductImage: " . $conn->error);
                    }
                }
                $conn->commit(); // Commit transaction if all successful
                $message = '<div class="success-message">Product added successfully!</div>';
            } else {
                throw new Exception("Error inserting product: " . $stmt_product->error);
            }
            $stmt_product->close();
        } else {
            throw new Exception("Failed to prepare statement for Product: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $message = '<div class="error-message">Error: ' . $e->getMessage() . '</div>';
        error_log("Add Product Error: " . $e->getMessage());
    }
}

// --- PHP Logic for Fetching Dropdown Data ---
// Function to fetch data for dropdowns
function fetchDropdownData($conn, $tableName, $idColumn = 'ID', $nameColumn = 'Name', $isActiveColumn = null, $extraColumns = null) {
    $data = [];
    $columns = [$idColumn, $nameColumn];
    if ($extraColumns) {
        $columns = array_merge($columns, (array)$extraColumns);
    }
    $columnList = '`' . implode('`, `', $columns) . '`';
    
    $query = "SELECT $columnList FROM `$tableName`";
    if ($isActiveColumn) {
        $query .= " WHERE `$isActiveColumn` = TRUE";
    }
    $query .= " ORDER BY `$nameColumn` ASC";

    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    } else {
        error_log("Failed to fetch data from $tableName: " . $conn->error);
    }
    return $data;
}

// Fetch data for all dropdowns (initial load)
$productCategories = fetchDropdownData($conn, 'ProductCategory', 'ID', 'Name', 'IsActive');
// Fetch all subcategories, including their parent category ID for filtering
$productSubCategories = fetchDropdownData($conn, 'ProductSubCategory', 'ID', 'Name', 'IsActive', 'ProductCategoryID');
$productBrands = fetchDropdownData($conn, 'Brand', 'ID', 'Name', 'IsActive');

$taxTypes = fetchDropdownData($conn, 'TaxType');
$conditions = fetchDropdownData($conn, 'ProductCondition');
$colors = fetchDropdownData($conn, 'Color');
$transmissions = fetchDropdownData($conn, 'Transmission');
$engineTypes = fetchDropdownData($conn, 'EngineType');
$bodyTypes = fetchDropdownData($conn, 'BodyType');
$vgas = fetchDropdownData($conn, 'Vga', 'ID', 'Name', 'IsActive');
$cpus = fetchDropdownData($conn, 'Cpu', 'ID', 'Name', 'IsActive');
$rams = fetchDropdownData($conn, 'Ram', 'ID', 'Name', 'IsActive');
$storages = fetchDropdownData($conn, 'Storage', 'ID', 'Name', 'IsActive');
$screens = fetchDropdownData($conn, 'Screen', 'ID', 'Name', 'IsActive');

// Dummy data for City, District, Commune (replace with actual DB fetches if you have these tables)
$cities = [
    ['ID' => 1, 'Name' => 'Phnom Penh'],
    ['ID' => 2, 'Name' => 'Siem Reap'],
    ['ID' => 3, 'Name' => 'Battambang']
];
$districts = [
    ['ID' => 101, 'Name' => 'Chamkarmon', 'CityID' => 1],
    ['ID' => 102, 'Name' => 'Daun Penh', 'CityID' => 1],
    ['ID' => 201, 'Name' => 'Siem Reap City', 'CityID' => 2]
];
$communes = [
    ['ID' => 10101, 'Name' => 'Boeung Keng Kang 1', 'DistrictID' => 101],
    ['ID' => 10102, 'Name' => 'Tuol Svay Prey 1', 'DistrictID' => 101],
    ['ID' => 20101, 'Name' => 'Slor Kram', 'DistrictID' => 201]
];


// --- PHP Logic for Displaying Products ---
$products = [];
$query_products = "
    SELECT
        p.ID, p.Name, p.Price, p.Discount, p.IsFreeDilivery, p.Description, p.Address, p.CreatedDate, p.IsActive,
        psc.Name AS SubCategoryName,
        pc.Name AS CategoryName,
        pb.Name AS BrandName,
        pcnd.Name AS ConditionName,
        GROUP_CONCAT(pi.Photo ORDER BY pi.SortOrder ASC) AS ProductPhotos
    FROM
        `Product` p
    LEFT JOIN
        `ProductSubCategory` psc ON p.ProductSubCategoryID = psc.ID
    LEFT JOIN
        `ProductCategory` pc ON psc.ProductCategoryID = pc.ID
    LEFT JOIN
        `Brand` pb ON p.ProductBrandID = pb.ID
    LEFT JOIN
        `ProductCondition` pcnd ON p.ConditionID = pcnd.ID
    LEFT JOIN
        `ProductImage` pi ON p.ID = pi.ProductID
    GROUP BY
        p.ID
    ORDER BY
        p.CreatedDate DESC
";

$result_products = $conn->query($query_products);
if ($result_products) {
    while ($row = $result_products->fetch_assoc()) {
        if ($row['ProductPhotos']) {
            $row['ProductPhotosArray'] = explode(',', $row['ProductPhotos']);
        } else {
            $row['ProductPhotosArray'] = [];
        }
        $products[] = $row;
    }
    $result_products->free();
} else {
    error_log("Failed to fetch products: " . $conn->error);
}

// Close connection if no other scripts will use it
// $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add & View Products</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1, h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }

        .message-container {
            margin-bottom: 20px;
            text-align: center;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }

        /* --- Form Styling --- */
        .add-product-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fdfdfd;
        }

        .form-group {
            margin-bottom: 15px;
            /* Added for hiding fields */
            display: block;
        }
        .form-group.hidden {
            display: none;
        }


        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="file"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Include padding in width */
            font-size: 16px;
            background-color: #fefefe;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }

        .form-actions {
            grid-column: 1 / -1; /* Span across all columns */
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .form-actions button {
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-actions button:hover {
            background-color: #0056b3;
        }

        /* Image Preview */
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 5px;
            min-height: 100px;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
        }
        .image-preview-container img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 5px;
            object-fit: cover;
            border: 1px solid #eee;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .image-preview-container:empty:before {
            content: "No images selected";
            color: #aaa;
            font-style: italic;
        }

        /* --- Product Display Styling --- */
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .product-card {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .product-card-image {
            width: 100%;
            height: 200px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .product-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-card-image .no-image-placeholder {
            color: #ccc;
            font-size: 50px;
        }

        .product-card-content {
            padding: 15px;
            flex-grow: 1;
        }

        .product-card-content h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 20px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-card-content .price {
            font-size: 22px;
            font-weight: bold;
            color: #e67e22; /* Orange color */
            margin-bottom: 5px;
        }

        .product-card-content .discount {
            font-size: 16px;
            color: #27ae60; /* Green for discount */
            margin-left: 10px;
        }

        .product-card-content .details {
            font-size: 14px;
            color: #777;
            margin-bottom: 8px;
        }
        .product-card-content .description {
            font-size: 14px;
            color: #555;
            line-height: 1.5;
            max-height: 60px; /* Limit description height */
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card-content .location {
            font-size: 13px;
            color: #888;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .product-card-content .date {
            font-size: 12px;
            color: #999;
            text-align: right;
            margin-top: 10px;
        }

        /* Responsive adjustments for dropdowns in search area */
        @media (max-width: 900px) {
            .khmer24-header .search-area .category-dropdown,
            .khmer24-header .search-area .subcategory-dropdown {
                width: calc(50% - 5px); /* Two dropdowns side-by-side */
            }
        }
        @media (max-width: 600px) {
            .khmer24-header .search-area .category-dropdown,
            .khmer24-header .search-area .subcategory-dropdown {
                width: 100%; /* Stack dropdowns */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add New Product</h1>
        <?php echo $message; // Display success/error messages ?>

        <form class="add-product-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_product" value="1">

            <div class="form-group" data-category-id="all">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="mainCategoryDropdownForm">Product Category:</label>
                <select id="mainCategoryDropdownForm" name="productCategoryID" required>
                    <option value="">Select Category</option>
                    <?php foreach ($productCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['ID']); ?>">
                            <?php echo htmlspecialchars($category['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="subCategoryDropdownForm">Product SubCategory:</label>
                <select id="subCategoryDropdownForm" name="productSubCategoryID" required>
                    <option value="">Select SubCategory</option>
                    <?php foreach ($productSubCategories as $subCategory): ?>
                        <option value="<?php echo htmlspecialchars($subCategory['ID']); ?>" data-category-id="<?php echo htmlspecialchars($subCategory['ProductCategoryID']); ?>">
                            <?php echo htmlspecialchars($subCategory['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="productBrandID">Brand:</label>
                <select id="productBrandID" name="productBrandID">
                    <option value="">Select Brand (Optional)</option>
                    <?php foreach ($productBrands as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand['ID']); ?>">
                            <?php echo htmlspecialchars($brand['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="taxTypeID">Tax Type:</label>
                <select id="taxTypeID" name="taxTypeID">
                    <option value="">Select Tax Type (Optional)</option>
                    <?php foreach ($taxTypes as $taxType): ?>
                        <option value="<?php echo htmlspecialchars($taxType['ID']); ?>">
                            <?php echo htmlspecialchars($taxType['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="conditionID">Condition:</label>
                <select id="conditionID" name="conditionID">
                    <option value="">Select Condition (Optional)</option>
                    <?php foreach ($conditions as $condition): ?>
                        <option value="<?php echo htmlspecialchars($condition['ID']); ?>">
                            <?php echo htmlspecialchars($condition['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="colorID">Color:</label>
                <select id="colorID" name="colorID">
                    <option value="">Select Color (Optional)</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?php echo htmlspecialchars($color['ID']); ?>">
                            <?php echo htmlspecialchars($color['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="transmissionID">Transmission Type (if applicable):</label>
                <select id="transmissionID" name="transmissionID">
                    <option value="">Select Transmission (Optional)</option>
                    <?php foreach ($transmissions as $transmission): ?>
                        <option value="<?php echo htmlspecialchars($transmission['ID']); ?>">
                            <?php echo htmlspecialchars($transmission['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="engineTypeID">Engine Type (if applicable):</label>
                <select id="engineTypeID" name="engineTypeID">
                    <option value="">Select Engine Type (Optional)</option>
                    <?php foreach ($engineTypes as $engineType): ?>
                        <option value="<?php echo htmlspecialchars($engineType['ID']); ?>">
                            <?php echo htmlspecialchars($engineType['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="bodyTypeID">Body Type (if applicable):</label>
                <select id="bodyTypeID" name="bodyTypeID">
                    <option value="">Select Body Type (Optional)</option>
                    <?php foreach ($bodyTypes as $bodyType): ?>
                        <option value="<?php echo htmlspecialchars($bodyType['ID']); ?>">
                            <?php echo htmlspecialchars($bodyType['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="vgaID">VGA (if applicable):</label>
                <select id="vgaID" name="vgaID">
                    <option value="">Select VGA (Optional)</option>
                    <?php foreach ($vgas as $vga): ?>
                        <option value="<?php echo htmlspecialchars($vga['ID']); ?>">
                            <?php echo htmlspecialchars($vga['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="cpuID">CPU (if applicable):</label>
                <select id="cpuID" name="cpuID">
                    <option value="">Select CPU (Optional)</option>
                    <?php foreach ($cpus as $cpu): ?>
                        <option value="<?php echo htmlspecialchars($cpu['ID']); ?>">
                            <?php echo htmlspecialchars($cpu['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="ramID">RAM (if applicable):</label>
                <select id="ramID" name="ramID">
                    <option value="">Select RAM (Optional)</option>
                    <?php foreach ($rams as $ram): ?>
                        <option value="<?php echo htmlspecialchars($ram['ID']); ?>">
                            <?php echo htmlspecialchars($ram['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="storageID">Storage (if applicable):</label>
                <select id="storageID" name="storageID">
                    <option value="">Select Storage (Optional)</option>
                    <?php foreach ($storages as $storage): ?>
                        <option value="<?php echo htmlspecialchars($storage['ID']); ?>">
                            <?php echo htmlspecialchars($storage['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="screenID">Screen (if applicable):</label>
                <select id="screenID" name="screenID">
                    <option value="">Select Screen (Optional)</option>
                    <?php foreach ($screens as $screen): ?>
                        <option value="<?php echo htmlspecialchars($screen['ID']); ?>">
                            <?php echo htmlspecialchars($screen['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="discount">Discount (%):</label>
                <input type="number" id="discount" name="discount" step="0.01" min="0" max="100">
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="isFreeDelivery">
                    <input type="checkbox" id="isFreeDelivery" name="isFreeDelivery"> Free Delivery
                </label>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="cityID">City:</label>
                <select id="cityID" name="cityID" required>
                    <option value="">Select City</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city['ID']); ?>">
                            <?php echo htmlspecialchars($city['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="districtID">District:</label>
                <select id="districtID" name="districtID" required>
                    <option value="">Select District</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?php echo htmlspecialchars($district['ID']); ?>">
                            <?php echo htmlspecialchars($district['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="communeID">Commune:</label>
                <select id="communeID" name="communeID" required>
                    <option value="">Select Commune</option>
                    <?php foreach ($communes as $commune): ?>
                        <option value="<?php echo htmlspecialchars($commune['ID']); ?>">
                            <?php echo htmlspecialchars($commune['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" required>
            </div>

            <div class="form-group" data-category-id="all">
                <label for="latitude">Latitude (Optional):</label>
                <input type="text" id="latitude" name="latitude" pattern="^-?\d{1,3}\.\d{6,}$" title="Enter a valid latitude (e.g., 11.5564)">
            </div>

            <div class="form-group" data-category-id="all">
                <label for="longitude">Longitude (Optional):</label>
                <input type="text" id="longitude" name="longitude" pattern="^-?\d{1,3}\.\d{6,}$" title="Enter a valid longitude (e.g., 104.9282)">
            </div>

            <div class="form-group" data-category-id="all,5">
                <label for="product_images">Product Images (Max 5):</label>
                <input type="file" id="product_images" name="product_images[]" accept="image/*" multiple="multiple">
                <div class="image-preview-container" id="imagePreviewContainer"></div>
            </div>

            <div class="form-actions">
                <button type="submit">Add Product</button>
            </div>
        </form>

        <h2>Existing Products</h2>
        <?php if (empty($products)): ?>
            <p style="text-align: center; color: #777;">No products found. Add one above!</p>
        <?php else: ?>
            <div class="product-list">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-card-image">
                            <?php if (!empty($product['ProductPhotosArray'])): ?>
                                <img src="<?php echo htmlspecialchars($product['ProductPhotosArray'][0]); ?>" alt="<?php echo htmlspecialchars($product['Name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-image no-image-placeholder"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-content">
                            <h3><?php echo htmlspecialchars($product['Name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['Price'], 2); ?>
                                <?php if ($product['Discount'] > 0): ?>
                                    <span class="discount">(<?php echo htmlspecialchars($product['Discount']); ?>% Off)</span>
                                <?php endif; ?>
                            </div>
                            <div class="details">
                                Category: <?php echo htmlspecialchars($product['CategoryName'] ?? 'N/A'); ?><br>
                                SubCategory: <?php echo htmlspecialchars($product['SubCategoryName'] ?? 'N/A'); ?><br>
                                Brand: <?php echo htmlspecialchars($product['BrandName'] ?? 'N/A'); ?><br>
                                Condition: <?php echo htmlspecialchars($product['ConditionName'] ?? 'N/A'); ?><br>
                                Free Delivery: <?php echo $product['IsFreeDilivery'] ? 'Yes' : 'No'; ?>
                            </div>
                            <p class="description"><?php echo nl2br(htmlspecialchars($product['Description'])); ?></p>
                            <div class="location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['Address']); ?>
                            </div>
                            <div class="date">Posted: <?php echo date('M d, Y', strtotime($product['CreatedDate'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Image Preview Logic ---
            const productImageInput = document.getElementById('product_images');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');

            if (productImageInput && imagePreviewContainer) {
                productImageInput.addEventListener('change', (event) => {
                    imagePreviewContainer.innerHTML = ''; // Clear previous previews

                    const files = event.target.files;
                    if (files.length > 5) {
                        alert('You can only upload a maximum of 5 images.');
                        productImageInput.value = ''; // Clear selected files
                        return;
                    }

                    for (const file of files) {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                imagePreviewContainer.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            }

            // --- Dynamic Form Field Logic ---
            const categoryDropdown = document.getElementById('mainCategoryDropdownForm');
            const formGroups = document.querySelectorAll('.add-product-form .form-group');

            function updateFormFields(selectedCategoryId) {
                formGroups.forEach(group => {
                    const categories = group.getAttribute('data-category-id');
                    if (!categories) return;

                    const validCategories = categories.split(',').map(id => id.trim());

                    if (validCategories.includes('all') || (selectedCategoryId && validCategories.includes(selectedCategoryId))) {
                        group.style.display = 'block';
                    } else {
                        group.style.display = 'none';
                    }
                });
            }

            // Initially, show all fields
            updateFormFields(null);

            // Add an event listener to the category dropdown
            categoryDropdown.addEventListener('change', (event) => {
                const selectedId = event.target.value;
                
                // Hide all form fields initially
                formGroups.forEach(group => group.style.display = 'none');
                
                // Show fields based on the selected category
                formGroups.forEach(group => {
                    const categories = group.getAttribute('data-category-id');
                    const validCategories = categories ? categories.split(',').map(id => id.trim()) : [];
                    
                    if (validCategories.includes('all') || validCategories.includes(selectedId)) {
                        group.style.display = 'block';
                    }
                });
            });

            // --- SubCategory Filtering Logic (NEW) ---
            const subCategoryDropdown = document.getElementById('subCategoryDropdownForm');
            const subCategoryOptions = subCategoryDropdown.querySelectorAll('option');

            // Hide all subcategory options on page load except the first one
            subCategoryOptions.forEach(option => {
                if (option.value !== '') {
                    option.style.display = 'none';
                }
            });

            categoryDropdown.addEventListener('change', (event) => {
                const selectedCategoryId = event.target.value;

                // Reset subcategory dropdown to its default state
                subCategoryDropdown.selectedIndex = 0;

                // Loop through all subcategory options
                subCategoryOptions.forEach(option => {
                    const parentCategoryId = option.getAttribute('data-category-id');
                    
                    // Show the 'Select SubCategory' option or if the category matches
                    if (option.value === '' || parentCategoryId === selectedCategoryId) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>