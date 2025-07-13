 <?php include"header.php"; ?>
<?php
// Enable full error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

// Check if a product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<h1>Product ID is not specified.</h1>";
    exit();
}

$product_id = (int)$_GET['id'];

// Get product details with category and location information
$sql = "SELECT 
            p.*, 
            c.name AS category_name,
            sc.name AS sub_category_name,
            prov.khmer_name AS province_name,
            dist.khmer_name AS district_name,
            comm.khmer_name AS commune_name,
            vill.khmer_name AS village_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN sub_categories sc ON p.sub_category_id = sc.id
        LEFT JOIN provinces prov ON p.province_id = prov.id
        LEFT JOIN districts dist ON p.district_id = dist.id
        LEFT JOIN communes comm ON p.commune_id = comm.id
        LEFT JOIN villages vill ON p.village_id = vill.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<h1>Prepare failed: " . htmlspecialchars($conn->error) . "</h1>";
    exit();
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

// Check if the product was found
if (!$product) {
    echo "<h1>Product not found.</h1>";
    exit();
}

// Get product images
$image_sql = "SELECT image_path FROM images WHERE product_id = ? ORDER BY id ASC";
$image_stmt = $conn->prepare($image_sql);
$image_stmt->bind_param("i", $product_id);
$image_stmt->execute();
$image_result = $image_stmt->get_result();
$images = $image_result->fetch_all(MYSQLI_ASSOC);
$image_stmt->close();

// Get product attributes
$attr_sql = "SELECT 
                 a.name AS attribute_name, 
                 a.unit, 
                 a.input_type,
                 pa.value AS value
            FROM product_attributes pa
            JOIN attributes a ON pa.attribute_id = a.id
            WHERE pa.product_id = ?
            ORDER BY a.name";
$attr_stmt = $conn->prepare($attr_sql);
$attr_stmt->bind_param("i", $product_id);
$attr_stmt->execute();
$attributes = $attr_stmt->get_result();
$product_attributes = [];
while ($row = $attributes->fetch_assoc()) {
    $product_attributes[$row['attribute_name']] = $row;
}
$attr_stmt->close();

$conn->close();

// Helper function to calculate time ago
function timeAgo($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate weeks without creating dynamic property
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array();
    
    if ($diff->y) $string[] = $diff->y . ' ឆ្នាំ';
    if ($diff->m) $string[] = $diff->m . ' ខែ';
    if ($weeks) $string[] = $weeks . ' សប្តាហ៍';
    if ($days) $string[] = $days . ' ថ្ងៃ';
    if ($diff->h) $string[] = $diff->h . ' ម៉ោង';
    if ($diff->i) $string[] = $diff->i . ' នាទី';
    if ($diff->s) $string[] = $diff->s . ' វិនាទី';

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' មុន' : 'ទើបតែ';
}

// Calculate discounted price
$original_price = $product['price'];
$discount = $product['discount'] ?? 0;
$discounted_price = $original_price - $discount;
$has_discount = $discount > 0;
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - លម្អិត</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Moul&family=Preahvihear&family=Suwannaphum&display=swap');

        body { font-family: 'Suwannaphum', 'Preahvihear', sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; }
        .breadcrumb { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #6c757d; }
        .breadcrumb a { text-decoration: none; color: #007bff; }
        .breadcrumb span { color: #212529; }
        .product-content { display: flex; flex-wrap: wrap; padding: 20px; gap: 20px; }
        .product-images { flex: 1 1 45%; min-width: 300px; }
        .main-image { width: 100%; max-height: 500px; object-fit: contain; border-radius: 8px; border: 1px solid #ddd; }
        .thumbnails { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .thumbnails img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; opacity: 0.7; border: 2px solid transparent; transition: opacity 0.2s, border-color 0.2s; }
        .thumbnails img:hover, .thumbnails img.active { opacity: 1; border-color: #007bff; }
        .product-details { flex: 1 1 50%; min-width: 300px; }
        .details-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .product-name { font-family: 'Moul', 'Suwannaphum', serif; font-size: 1.8em; color: #2c3e50; margin: 0; }
        .favorite-btn { font-size: 1.5em; color: #ccc; cursor: pointer; transition: color 0.2s; }
        .favorite-btn.favorited { color: #e74c3c; }
        .ad-meta { font-size: 0.9em; color: #888; margin-bottom: 20px; }
        .price-section { margin: 15px 0; }
        .price { font-size: 2.2em; color: #e74c3c; font-weight: bold; margin: 0; }
        .original-price { font-size: 1.2em; color: #888; text-decoration: line-through; margin-right: 10px; }
        .discount-badge { background: #ff4757; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; margin-left: 10px; }
        .free-shipping { background: #2ed573; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.9em; font-weight: bold; margin-top: 10px; display: inline-block; }
        .condition-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; margin-top: 10px; display: inline-block; }
        .condition-new { background: #2ed573; color: white; }
        .condition-used { background: #ffa502; color: white; }
        .condition-refurbished { background: #3742fa; color: white; }
        .info-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 15px 10px; border-top: 1px solid #eee; padding-top: 20px; }
        .info-label { font-weight: bold; color: #555; }
        .info-value { color: #333; }
        .product-description-section { margin-top: 30px; }
        .section-title { font-size: 1.5em; font-weight: bold; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 0; }
        .contact-info { margin-top: 20px; display: flex; flex-direction: column; gap: 10px; }
        .contact-info a { text-decoration: none; color: #007bff; font-weight: bold; }
        .map-section { margin-top: 30px; }
        .map-embed { width: 100%; height: 400px; border: none; border-radius: 8px; }
        .location-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .location-info h4 { margin: 0 0 10px 0; color: #2c3e50; }
        .location-text { color: #666; }
        
        @media (max-width: 768px) {
            .product-content { flex-direction: column; }
            .product-images, .product-details { flex: 1 1 100%; min-width: auto; }
            .container { margin: 10px; }
            .map-embed { height: 300px; }
        }
    </style>
</head>
<body>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="#">ទំព័រដើម</a> &gt; 
            <a href="#"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></a> &gt;
            <a href="#"><?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?></a> &gt;
            <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <div class="product-content">
    <div class="product-images">
        <img id="mainImage" class="main-image" 
             src="products/<?php echo htmlspecialchars($images[0]['image_path'] ?? ''); ?>" 
             alt="<?php echo htmlspecialchars($product['name']); ?>">
        
        <div class="thumbnails">
            <?php foreach ($images as $index => $image): ?>
                <img src="products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                     data-src="products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                     alt="Thumbnail <?php echo $index + 1; ?>"
                     class="thumbnail-img <?php echo ($index === 0) ? 'active' : ''; ?>">
            <?php endforeach; ?>
        </div>
    </div>
</div>

            <div class="product-details">
                <div class="details-header">
                    <h1 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <i class="far fa-heart favorite-btn"></i>
                </div>
                
                <div class="ad-meta">
                    <span>AD ID: <?php echo htmlspecialchars($product['id']); ?></span> • 
                    <span><?php echo htmlspecialchars(timeAgo($product['created_at'])); ?></span>
                </div>

                <div class="price-section">
                    <?php if ($has_discount): ?>
                        <div>
                            <span class="original-price">$<?php echo number_format($original_price, 2); ?></span>
                            <span class="discount-badge">-$<?php echo number_format($discount, 2); ?></span>
                        </div>
                        <div class="price">$<?php echo number_format($discounted_price, 2); ?></div>
                    <?php else: ?>
                        <div class="price">$<?php echo number_format($original_price, 2); ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($product['free_shipping']): ?>
                    <div class="free-shipping">
                        <i class="fas fa-truck"></i> ដឹកជញ្ជូនឥតគិតថ្លៃ
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['condition'])): ?>
                    <div class="condition-badge <?php 
                        echo strtolower($product['condition']) === 'new' ? 'condition-new' : 
                            (strtolower($product['condition']) === 'used' ? 'condition-used' : 'condition-refurbished'); 
                    ?>">
                        <?php 
                        $condition_labels = [
                            'new' => 'ថ្មី',
                            'used' => 'បានប្រើ',
                            'refurbished' => 'បានជួសជុល'
                        ];
                        echo $condition_labels[strtolower($product['condition'])] ?? htmlspecialchars($product['condition']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-label">ប្រភេទ:</div>
                    <div class="info-value"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></div>
                    
                    <div class="info-label">ប្រភេទរង:</div>
                    <div class="info-value"><?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?></div>
                    
                    <?php if (isset($product_attributes['ម៉ាក'])): ?>
                        <div class="info-label">ម៉ាក:</div>
                        <div class="info-value"><?php echo htmlspecialchars($product_attributes['ម៉ាក']['value']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($product_attributes['ទំហំផ្ទុក'])): ?>
                        <div class="info-label">ទំហំផ្ទុក:</div>
                        <div class="info-value"><?php echo htmlspecialchars($product_attributes['ទំហំផ្ទុក']['value']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['condition'])): ?>
                        <div class="info-label">លក្ខខណ្ឌ:</div>
                        <div class="info-value"><?php echo htmlspecialchars($product['condition']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['province_name']) || !empty($product['district_name']) || !empty($product['commune_name']) || !empty($product['village_name'])): ?>
                        <div class="info-label">ទីតាំង:</div>
                        <div class="info-value">
                            <?php
                            $location_parts = array_filter([
                                !empty($product['village_name']) ? $product['village_name'] : null,
                                !empty($product['commune_name']) ? $product['commune_name'] : null,
                                !empty($product['district_name']) ? $product['district_name'] : null,
                                !empty($product['province_name']) ? $product['province_name'] : null
                            ]);
                            
                            if (!empty($location_parts)) {
                                echo htmlspecialchars(implode(', ', $location_parts));
                            } else {
                                echo 'មិនបានបញ្ជាក់ទីតាំង';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="contact-info">
                    <?php if (!empty($product['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($product['phone']); ?>">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($product['phone']); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['telegram'])): ?>
                        <a href="https://t.me/<?php echo htmlspecialchars($product['telegram']); ?>" target="_blank">
                            <i class="fab fa-telegram"></i> <?php echo htmlspecialchars($product['telegram']); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['facebook'])): ?>
                        <a href="<?php echo htmlspecialchars($product['facebook']); ?>" target="_blank">
                            <i class="fab fa-facebook"></i> Facebook
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="product-description-section">
            <h2 class="section-title">ពិពណ៌នា</h2>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
        </div>

        <?php if (!empty($product['map_embed'])): ?>
            <div class="map-section">
                <h2 class="section-title">ទីតាំង</h2>
                <div class="map-embed">
                    <?php echo $product['map_embed']; ?>
                </div>
                <div class="location-info">
                    <h4>អាស័យដ្ឋាន</h4>
                    <div class="location-text">
                        <?php
                        $location_parts = array_filter([
                            !empty($product['village_name']) ? $product['village_name'] : null,
                            !empty($product['commune_name']) ? $product['commune_name'] : null,
                            !empty($product['district_name']) ? $product['district_name'] : null,
                            !empty($product['province_name']) ? $product['province_name'] : null
                        ]);
                        
                        if (!empty($location_parts)) {
                            echo htmlspecialchars(implode(', ', $location_parts));
                        } else {
                            echo 'មិនបានបញ្ជាក់ទីតាំង';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript to handle thumbnail clicks
        const mainImage = document.getElementById('mainImage');
        const thumbnails = document.querySelectorAll('.thumbnail-img');

        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', () => {
                // Update main image source
                mainImage.src = thumbnail.dataset.src;

                // Update active class
                thumbnails.forEach(img => img.classList.remove('active'));
                thumbnail.classList.add('active');
            });
        });

        // Set initial active thumbnail
        if (thumbnails.length > 0) {
            thumbnails[0].classList.add('active');
        }

        // Favorite button functionality
        const favoriteBtn = document.querySelector('.favorite-btn');
        favoriteBtn.addEventListener('click', () => {
            favoriteBtn.classList.toggle('favorited');
            favoriteBtn.classList.toggle('far');
            favoriteBtn.classList.toggle('fas');
        });
    </script>
</body>
</html>