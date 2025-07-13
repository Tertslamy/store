<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: view_products.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Get product details
$sql = "SELECT p.*, c.name as category_name,
               pr.khmer_name as province_name,
               d.khmer_name as district_name,
               com.khmer_name as commune_name,
               v.khmer_name as village_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN provinces pr ON p.province_id = pr.id
        LEFT JOIN districts d ON p.district_id = d.id
        LEFT JOIN communes com ON p.commune_id = com.id
        LEFT JOIN villages v ON p.village_id = v.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: view_products.php");
    exit();
}

// Get product images
$image_sql = "SELECT * FROM images WHERE product_id = ? ORDER BY id";
$image_stmt = $conn->prepare($image_sql);
$image_stmt->bind_param("i", $product_id);
$image_stmt->execute();
$images = $image_stmt->get_result();

// Get product attributes
$attr_sql = "SELECT pa.value, a.name as attribute_name, a.unit
             FROM product_attributes pa
             JOIN attributes a ON pa.attribute_id = a.id
             WHERE pa.product_id = ?";
$attr_stmt = $conn->prepare($attr_sql);
$attr_stmt->bind_param("i", $product_id);
$attr_stmt->execute();
$attributes = $attr_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($product['name']); ?> - លម្អិតផលិតផល</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .product-header { display: flex; gap: 30px; margin-bottom: 30px; }
        .product-images { flex: 1; }
        .product-info { flex: 1; }
        .main-image { width: 100%; height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 15px; }
        .image-thumbnails { display: flex; gap: 10px; flex-wrap: wrap; }
        .thumbnail { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; }
        .thumbnail.active { border-color: #3498db; }
        .thumbnail:hover { border-color: #2980b9; }
        .product-title { font-size: 28px; font-weight: bold; margin-bottom: 15px; }
        .product-price { font-size: 24px; color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        .product-meta { display: flex; gap: 10px; margin-bottom: 15px; }
        .badge { padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .badge-category { background: #3498db; color: white; }
        .badge-condition { background: #2ecc71; color: white; }
        .badge-shipping { background: #f39c12; color: white; }
        .product-description { margin: 20px 0; line-height: 1.6; }
        .product-attributes { margin: 20px 0; }
        .attribute-row { display: flex; padding: 10px 0; border-bottom: 1px solid #eee; }
        .attribute-name { font-weight: bold; width: 150px; }
        .attribute-value { flex: 1; }
        .location-info { background: #ecf0f1; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .contact-info { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .map-embed { width: 100%; height: 300px; border: none; border-radius: 8px; margin: 20px 0; }
        .back-btn { background: #95a5a6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-bottom: 20px; }
        .back-btn:hover { background: #7f8c8d; }
        .no-image { background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 400px; border: 1px dashed #ddd; color: #999; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="view_products.php" class="back-btn">← ត្រឡប់ទៅបញ្ជីផលិតផល</a>
        
        <div class="product-header">
            <div class="product-images">
                <?php if ($images->num_rows > 0): ?>
                    <?php $first_image = $images->fetch_assoc(); ?>
                    <?php if (file_exists($first_image['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($first_image['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($first_image['alt_text']); ?>" 
                             class="main-image" id="mainImage">
                    <?php else: ?>
                        <div class="no-image">មិនមានរូបភាព</div>
                    <?php endif; ?>
                    
                    <?php if ($images->num_rows > 1): ?>
                        <div class="image-thumbnails">
                            <?php 
                            $images->data_seek(0); // Reset pointer
                            $index = 0;
                            while ($image = $images->fetch_assoc()): 
                                if (file_exists($image['image_path'])):
                            ?>
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($image['alt_text']); ?>" 
                                     class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="changeMainImage(this)">
                                <?php $index++; ?>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-image">មិនមានរូបភាព</div>
                <?php endif; ?>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-price">
                    <?php if ($product['discount'] > 0): ?>
                        <span style="text-decoration: line-through; color: #999; font-size: 18px;">$<?php echo number_format($product['price'], 2); ?></span>
                        <span>$<?php echo number_format($product['price'] - $product['discount'], 2); ?></span>
                    <?php else: ?>
                        $<?php echo number_format($product['price'], 2); ?>
                    <?php endif; ?>
                </div>
                
                <div class="product-meta">
                    <span class="badge badge-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <span class="badge badge-condition"><?php echo htmlspecialchars($product['condition']); ?></span>
                    <?php if ($product['free_shipping']): ?>
                        <span class="badge badge-shipping">ដឹកជញ្ជូនដោយឥតគិតថ្លៃ</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($product['description'])): ?>
                    <div class="product-description">
                        <h3>ពិពណ៌នា</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($attributes->num_rows > 0): ?>
                    <div class="product-attributes">
                        <h3>លក្ខណៈពិសេស</h3>
                        <?php while ($attr = $attributes->fetch_assoc()): ?>
                            <div class="attribute-row">
                                <div class="attribute-name"><?php echo htmlspecialchars($attr['attribute_name']); ?>:</div>
                                <div class="attribute-value">
                                    <?php echo htmlspecialchars($attr['value']); ?>
                                    <?php if (!empty($attr['unit'])): ?>
                                        <?php echo htmlspecialchars($attr['unit']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($product['province_name'])): ?>
            <div class="location-info">
                <h3>📍 ទីតាំង</h3>
                <p>
                    <?php echo htmlspecialchars($product['province_name']); ?>
                    <?php if (!empty($product['district_name'])): ?>
                        > <?php echo htmlspecialchars($product['district_name']); ?>
                    <?php endif; ?>
                    <?php if (!empty($product['commune_name'])): ?>
                        > <?php echo htmlspecialchars($product['commune_name']); ?>
                    <?php endif; ?>
                    <?php if (!empty($product['village_name'])): ?>
                        > <?php echo htmlspecialchars($product['village_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($product['phone']) || !empty($product['telegram']) || !empty($product['facebook'])): ?>
            <div class="contact-info">
                <h3>📞 ព័ត៌មានទំនាក់ទំនង</h3>
                <?php if (!empty($product['phone'])): ?>
                    <p><strong>លេខទូរស័ព្ទ:</strong> <?php echo htmlspecialchars($product['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($product['telegram'])): ?>
                    <p><strong>Telegram:</strong> <?php echo htmlspecialchars($product['telegram']); ?></p>
                <?php endif; ?>
                <?php if (!empty($product['facebook'])): ?>
                    <p><strong>Facebook:</strong> <?php echo htmlspecialchars($product['facebook']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($product['map_embed'])): ?>
            <div>
                <h3>🗺️ ផែនទីទីតាំង</h3>
                <iframe src="<?php echo htmlspecialchars($product['map_embed']); ?>" 
                        class="map-embed" 
                        allowfullscreen="" 
                        loading="lazy">
                </iframe>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; color: #666; font-size: 14px;">
            <p><strong>បានបញ្ចូលនៅ:</strong> <?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?></p>
        </div>
    </div>

    <script>
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('mainImage');
            const thumbnails = document.querySelectorAll('.thumbnail');
            
            // Remove active class from all thumbnails
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            
            // Add active class to clicked thumbnail
            thumbnail.classList.add('active');
            
            // Change main image
            mainImage.src = thumbnail.src;
            mainImage.alt = thumbnail.alt;
        }
    </script>
</body>
</html>