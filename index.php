 <?php include"header.php"; ?>

<?php
// Include the database connection file
include 'db.php';

// Pagination settings
$products_per_page = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $products_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build WHERE clause for search and filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Determine sort order
$order_by = match($sort_by) {
    'price_low' => 'ORDER BY (p.price - p.discount) ASC',
    'price_high' => 'ORDER BY (p.price - p.discount) DESC',
    'name' => 'ORDER BY p.name ASC',
    'oldest' => 'ORDER BY p.created_at ASC',
    default => 'ORDER BY p.created_at DESC'
};

// Count total products for pagination
$count_sql = "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $products_per_page);

// Get products with pagination
$sql = "SELECT p.*, c.name as category_name, 
               (SELECT image_path FROM images WHERE product_id = p.id ORDER BY id LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause
        $order_by
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$all_params = array_merge($params, [$products_per_page, $offset]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$result = $stmt->get_result();

// Get all categories for filter dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
$categories = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}
?>
<include
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ម៉ាស៊ីន Tablets និង កុំព្យូទ័រ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Arial', sans-serif; 
           
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin: 20px;
            padding: 20px 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-icons {
            display: flex;
            gap: 15px;
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .header-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .filters {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filters-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-input {
            min-width: 280px;
        }
        
        .btn { 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 10px; 
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .results-info {
            margin: 20px;
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .product-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 25px; 
            margin: 20px;
            padding-bottom: 40px;
        }
        
        .product-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .product-image-container {
            position: relative;
            overflow: hidden;
        }
        
        .product-image { 
            width: 100%; 
            height: 220px; 
            object-fit: cover; 
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .product-overlay {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }
        
        .product-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .product-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-title { 
            font-size: 18px; 
            font-weight: 700; 
            margin-bottom: 10px; 
            color: #2c3e50;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price { 
            color: #e74c3c; 
            font-size: 22px; 
            font-weight: 800; 
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-original {
            text-decoration: line-through;
            color: #999;
            font-size: 16px;
            font-weight: 500;
        }
        
        .product-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .product-category { 
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white; 
            padding: 6px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 600;
        }
        
        .product-condition { 
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white; 
            padding: 6px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 600;
        }
        
        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-meta { 
            margin-top: auto; 
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px; 
            color: #999;
        }
        
        .no-image { 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 220px; 
            color: #999; 
            font-size: 16px;
            font-weight: 500;
        }
        
        .free-shipping {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 20px;
        }
        
        .pagination a, .pagination span {
            padding: 12px 18px;
            text-decoration: none;
            border-radius: 10px;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .no-products {
            text-align: center;
            margin: 50px 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .no-products h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 24px;
        }
        
        .no-products p {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .discount-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                margin: 10px;
            }
            
            .filters {
                margin: 10px;
            }
            
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: auto;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
                margin: 10px;
                gap: 20px;
            }
            
            .results-info {
                margin: 10px;
            }
        }
    </style>
</head>
<body>
  

    <!-- Search and Filter Section -->
    <div class="filters">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label for="search">
                    <i class="fas fa-search"></i> ស្វែងរក
                </label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="ស្វែងរកផលិតផល..." class="search-input">
            </div>
            
            <div class="filter-group">
                <label for="category">
                    <i class="fas fa-list"></i> ប្រភេទ
                </label>
                <select name="category" id="category">
                    <option value="0">ប្រភេទទាំងអស់</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="sort">
                    <i class="fas fa-sort"></i> តម្រៀប
                </label>
                <select name="sort" id="sort">
                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>ថ្មីបំផុត</option>
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>ចាស់បំផុត</option>
                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>តម្លៃទាប</option>
                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>តម្លៃខ្ពស់</option>
                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>តាមឈ្មោះ</option>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> ស្វែងរក
                </button>
            </div>
            
            <?php if (!empty($search) || $category_filter > 0): ?>
            <div class="filter-group">
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-times"></i> សម្អាត
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results Info -->
    <?php if ($total_products > 0): ?>
        <div class="results-info">
            <i class="fas fa-info-circle"></i>
            បានរកឃើញ <strong><?php echo number_format($total_products); ?></strong> ផលិតផល
            <?php if ($total_pages > 1): ?>
                (ទំព័រ <?php echo $page; ?> នៃ <?php echo $total_pages; ?>)
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <div class="product-grid">
        <?php while ($product = $result->fetch_assoc()): ?>
            <a href="view_product.php?id=<?php echo $product['id']; ?>" class="product-card">
               <div class="product-image-container">
    <?php 
        $imageFile = $product['main_image'];
        $webPath = 'products/' . $imageFile;
        $serverPath = __DIR__ . '/products/' . $imageFile;

        if (!empty($imageFile) && file_exists($serverPath)): 
    ?>
        <img src="<?php echo htmlspecialchars($webPath); ?>" 
             alt="<?php echo htmlspecialchars($product['name']); ?>" 
             class="product-image">
    <?php else: ?>
        <div class="no-image">
            <i class="fas fa-image"></i> មិនមានរូបភាព
        </div>
    <?php endif; ?>

    <?php if ($product['discount'] > 0): ?>
        <div class="discount-badge">
            -<?php echo round(($product['discount'] / $product['price']) * 100); ?>%
        </div>
    <?php endif; ?>

    <?php if ($product['free_shipping']): ?>
        <div class="product-overlay">
            <span class="product-badge">
                <i class="fas fa-truck"></i> ឥតគិតថ្លៃ
            </span>
        </div>
    <?php endif; ?>
</div>

                
                <div class="product-content">
                    <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                    
                    <div class="product-tags">
                        <?php if (!empty($product['category_name'])): ?>
                            <span class="product-category">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                            </span>
                        <?php endif; ?>
                        <span class="product-condition">
                            <i class="fas fa-star"></i> <?php echo htmlspecialchars($product['condition']); ?>
                        </span>
                    </div>
                    
                    <div class="product-price">
                        <?php if ($product['discount'] > 0): ?>
                            <span class="price-original">
                                $<?php echo number_format($product['price'], 2); ?>
                            </span>
                            <span>$<?php echo number_format($product['price'] - $product['discount'], 2); ?></span>
                        <?php else: ?>
                            $<?php echo number_format($product['price'], 2); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-meta">
                        <span>
                            <i class="fas fa-calendar"></i>
                            <?php echo date('d/m/Y', strtotime($product['created_at'])); ?>
                        </span>
                        <?php if ($product['free_shipping']): ?>
                            <span class="free-shipping">
                                <i class="fas fa-shipping-fast"></i> ដឹកជញ្ជូនឥតគិតថ្លៃ
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> មុន
                </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++): 
            ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    បន្ត <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- No Products Message -->
    <?php if ($total_products == 0): ?>
        <div class="no-products">
            <?php if (!empty($search) || $category_filter > 0): ?>
                <h3><i class="fas fa-search"></i> រកមិនឃើញផលិតផលដែលស្វែងរក</h3>
                <p>សូមព្យាយាមដោយប្រើពាក្យគន្លឹះផ្សេង ឬសម្អាតតម្រងស្វែងរក</p>
                <p><a href="?" class="btn"><i class="fas fa-times"></i> សម្អាតការស្វែងរក</a></p>
            <?php else: ?>
                <h3><i class="fas fa-box-open"></i> មិនទាន់មានផលិតផលណាមួយទេ</h3>
                <p>ចាប់ផ្តើមដោយការបញ្ចូលផលិតផលដំបូងរបស់អ្នក</p>
                <p><a href="add_product.php" class="btn"><i class="fas fa-plus"></i> បញ្ចូលផលិតផលដំបូង</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php 
    $stmt->close();
    $count_stmt->close();
    $conn->close(); 
    ?>
</body>
</html>