<?php
// upload_config.php - Configuration for file uploads

// Upload directory
define('UPLOAD_DIR', 'uploads/');

// Maximum file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed image types
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/png', 
    'image/gif',
    'image/webp'
]);

// Allowed file extensions
define('ALLOWED_EXTENSIONS', [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp'
]);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Function to validate uploaded file
function validateUploadedFile($file, $key = 0) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name'][$key]) || empty($file['tmp_name'][$key])) {
        $errors[] = "មិនមានឯកសារត្រូវបានជ្រើសរើស";
        return $errors;
    }
    
    // Check for upload errors
    if ($file['error'][$key] !== UPLOAD_ERR_OK) {
        switch ($file['error'][$key]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "ឯកសារធំពេក";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "ឯកសារបានបញ្ចូលមិនគ្រប់";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = "មិនមានថតបណ្ដោះអាសន្ន";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = "មិនអាចសរសេរឯកសារ";
                break;
            default:
                $errors[] = "មានបញ្ហាក្នុងការបញ្ចូលឯកសារ";
        }
        return $errors;
    }
    
    // Check file size
    if ($file['size'][$key] > MAX_FILE_SIZE) {
        $errors[] = "ឯកសារធំពេក (អតិបរមា " . (MAX_FILE_SIZE / 1024 / 1024) . "MB)";
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name'][$key]);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
        $errors[] = "ប្រភេទឯកសារមិនត្រឹមត្រូវ";
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'][$key], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "កន្ទុយឯកសារមិនត្រឹមត្រូវ";
    }
    
    return $errors;
}

// Function to generate unique filename
function generateUniqueFilename($product_id, $index, $original_filename) {
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return 'product_' . $product_id . '_' . $index . '_' . time() . '.' . $file_extension;
}

// Function to resize image (optional)
function resizeImage($source_path, $destination_path, $max_width = 800, $max_height = 600) {
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $mime_type = $image_info['mime'];
    
    // Calculate new dimensions
    $ratio = min($max_width / $width, $max_height / $height);
    if ($ratio < 1) {
        $new_width = $width * $ratio;
        $new_height = $height * $ratio;
    } else {
        $new_width = $width;
        $new_height = $height;
    }
    
    // Create image resource
    switch ($mime_type) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    // Create new image
    $destination = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Save image
    switch ($mime_type) {
        case 'image/jpeg':
            imagejpeg($destination, $destination_path, 85);
            break;
        case 'image/png':
            imagepng($destination, $destination_path);
            break;
        case 'image/gif':
            imagegif($destination, $destination_path);
            break;
        case 'image/webp':
            imagewebp($destination, $destination_path, 85);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}
?>