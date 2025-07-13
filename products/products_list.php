<?php
include 'db.php';

// Get all products with their images
$sql = "SELECT p.*, c.name as category_name, 
               (SELECT image_path FROM images WHERE product_id = p.id ORDER BY id LIMIT 1) as main_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>បញ្ជីផលិតផល</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .product-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .product-image { width: 100%; height: 200px; object-fit: cover; border-radius: 4px; }
        .product-title { font-size: 18px; font-weight: bold; margin: 10px 0; }
        .product-price { color: #e74c3c; font-size: 16px; font-weight: bold; }
        .product-category { background: #3498db; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .product-condition { background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .product-meta { margin-top: 10px; font-size: 14px; color: #666; }
        .no-image { background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 200px; border: 1px dashed #ddd; color: #999; }
        .header { display: flex; justify-content: between; align-items: center; margin-bottom: 20px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 បញ្ជីផលិតផល</h1>
        <a href="add_product.php" class="btn">+ បញ្ចូលផលិតផលថ្មី</a>
    </div>

    <div class="product-grid">
        <?php while ($product = $result->fetch_assoc()): ?>
            <div class="product-card">
                <?php if (!empty($product['main_image']) && file_exists($product['main_image'])): ?>
                    <img src="<?php echo htmlspecialchars($product['main_image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-image">
                <?php else: ?>
                    <div class="no-image">មិនមានរូបភាព</div>
                <?php endif; ?>
                
                <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                
                <div style="margin: 10px 0;">
                    <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <span class="product-condition"><?php echo htmlspecialchars($product['condition']); ?></span>
                </div>
                
                <div class="product-price">
                    <?php if ($product['discount'] > 0): ?>
                        <span style="text-decoration: line-through; color: #999;">$<?php echo number_format($product['price'], 2); ?></span>
                        <span>$<?php echo number_format($product['price'] - $product['discount'], 2); ?></span>
                    <?php else: ?>
                        $<?php echo number_format($product['price'], 2); ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($product['description'])): ?>
                    <div class="product-meta">
                        <?php echo nl2br(htmlspecialchars(substr($product['description'], 0, 100))); ?>
                        <?php if (strlen($product['description']) > 100): ?>...<?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-meta">
                    <strong>បានបញ្ចូលនៅ:</strong> <?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?>
                </div>
                
                <?php if ($product['free_shipping']): ?>
                    <div style="margin-top: 10px; color: #27ae60;">
                        🚚 ដឹកជញ្ជូនដោយឥតគិតថ្លៃ
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn" style="font-size: 14px; padding: 5px 15px;">មើលលម្អិត</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <?php if ($result->num_rows == 0): ?>
        <div style="text-align: center; margin-top: 50px; color: #666;">
            <h3>មិនទាន់មានផលិតផលណាមួយទេ</h3>
            <p><a href="add_product.php" class="btn">បញ្ចូលផលិតផលដំបូង</a></p>
        </div>
    <?php endif; ?>

</body>
</html>