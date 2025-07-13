<?php
// my_profile.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Include mysqli database connection
// Assuming 'db.php' contains:
// $conn = new mysqli('localhost', 'username', 'password', 'database_name');
// if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
include "db.php";

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$user = [];

// --- Fetch Genders for dropdown ---
$genders = [];
$genders_result = $conn->query("SELECT ID, Name FROM Gender ORDER BY Name");
if ($genders_result) {
    while ($g = $genders_result->fetch_assoc()) {
        $genders[] = $g;
    }
    $genders_result->free();
} else {
    error_log("Error fetching genders: " . $conn->error);
}

// --- Fetch Provinces for dropdown (from 'provinces' table) ---
$provinces = [];
$provinces_result = $conn->query("SELECT id, name, khmer_name FROM provinces ORDER BY name");
if ($provinces_result) {
    while ($p = $provinces_result->fetch_assoc()) {
        $provinces[] = $p;
    }
    $provinces_result->free();
} else {
    error_log("Error fetching provinces: " . $conn->error);
}


// --- Handle Form Submission for Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = substr(trim($_POST['first_name']), 0, 50);
    $lastName = substr(trim($_POST['last_name']), 0, 50);    
    $genderID = !empty($_POST['gender_id']) ? (int)$_POST['gender_id'] : NULL;
    $birthday = empty(trim($_POST['birthday'])) ? NULL : trim($_POST['birthday']);
    $company = substr(trim($_POST['company']), 0, 100) ?: NULL;
    $phoneNumber = substr(trim($_POST['phone_number']), 0, 10);    
    $phoneNumber2 = substr(trim($_POST['phone_number2']), 0, 10) ?: NULL;
    $phoneNumber3 = substr(trim($_POST['phone_number3']), 0, 10) ?: NULL;
    $address = substr(trim($_POST['address']), 0, 255) ?: NULL;
    $provinceID = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : NULL;
    $districtID = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : NULL;
    $communeID = !empty($_POST['commune_id']) ? (int)$_POST['commune_id'] : NULL;
    $email = substr(trim($_POST['email']), 0, 150);

    if (empty($firstName) || empty($lastName) || empty($phoneNumber) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Required fields and valid email are necessary.';
        $messageType = 'error';
    } elseif (strlen($phoneNumber) > 10) {
        $message = 'Primary Phone Number must be 10 digits or less.';
        $messageType = 'error';
    } else {
        // Re-usable function for handling picture upload/removal for both profile and cover pictures
        function handlePictureUpdate($fileInputName, $removeFlagName, $uploadSubDir, $currentPathInDb, $userId, $conn) {
            global $message, $messageType; // Allow function to set global messages
            $newPath = $currentPathInDb; // Assume current path unless changed

            // Handle new upload
            if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileInputName];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileSize = $file['size'];
                $fileError = $file['error'];

                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
                $maxFileSize = ($fileInputName === 'profile_picture') ? (2 * 1024 * 1024) : (5 * 1024 * 1024); // 2MB for profile, 5MB for cover

                if (in_array($fileExt, $allowedExtensions) && $fileError === 0 && $fileSize < $maxFileSize) {
                    $uniqueFileName = uniqid($fileInputName . '_', true) . "." . $fileExt;
                    $uploadDir = 'uploads/' . $uploadSubDir . '/';
                    
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                    $fileDestination = $uploadDir . $uniqueFileName;
                    
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        if (!empty($currentPathInDb) && file_exists($currentPathInDb)) {
                            unlink($currentPathInDb); // Delete old file
                        }
                        $newPath = $fileDestination;
                    } else {
                        $message .= "Failed to upload $fileInputName.<br>";
                        $messageType = 'error';
                    }
                } else {
                    if (!in_array($fileExt, $allowedExtensions)) $message .= "Invalid type for $fileInputName. Allowed: JPG, JPEG, PNG, GIF.<br>";
                    if ($fileError !== 0) $message .= "Error uploading $fileInputName (code: $fileError).<br>";
                    if ($fileSize >= $maxFileSize) $message .= "$fileInputName is too large (Max " . ($maxFileSize / 1024 / 1024) . "MB).<br>";
                    $messageType = 'error';
                }
            } elseif (isset($_POST[$removeFlagName]) && $_POST[$removeFlagName] == '1') {
                // Handle removal
                if (!empty($currentPathInDb) && file_exists($currentPathInDb)) {
                    unlink($currentPathInDb); // Delete actual file
                }
                $newPath = NULL; // Set path in DB to NULL
            }
            return $newPath;
        }

        // Fetch current user data to get existing picture paths before updating
        $stmt_current_user_pics = $conn->prepare("SELECT ProfilePicture, CoverPicture FROM User WHERE ID = ?");
        $stmt_current_user_pics->bind_param("i", $userId);
        $stmt_current_user_pics->execute();
        $current_user_pics_result = $stmt_current_user_pics->get_result();
        $current_user_pics = $current_user_pics_result->fetch_assoc();
        $stmt_current_user_pics->close();
        if ($current_user_pics_result) $current_user_pics_result->free();

        $profilePicturePathToUpdate = handlePictureUpdate('profile_picture', 'remove_profile_picture', 'profile_pictures', $current_user_pics['ProfilePicture'], $userId, $conn);
        $coverPicturePathToUpdate = handlePictureUpdate('cover_picture', 'remove_cover_picture', 'cover_pictures', $current_user_pics['CoverPicture'], $userId, $conn); // NEW

        if (empty($message)) { // Proceed with DB update only if no file errors
            $stmt = $conn->prepare("
                UPDATE User SET    
                    FirstName = ?, LastName = ?, GenderID = ?, Birthday = ?, Company = ?,    
                    PhoneNumber = ?, PhoneNumber2 = ?, PhoneNumber3 = ?, Address = ?,    
                    ProvinceID = ?, DistrictID = ?, CommuneID = ?, Email = ?,    
                    ProfilePicture = ?, CoverPicture = ? -- NEW: CoverPicture column
                WHERE ID = ?
            ");
            
            if ($stmt) {
                // NEW: Updated bind_param for CoverPicture (add 's' at the end before userId 'i')
                $stmt->bind_param("ssissssssiiisssi",
                    $firstName, $lastName, $genderID, $birthday, $company,
                    $phoneNumber, $phoneNumber2, $phoneNumber3, $address,
                    $provinceID, $districtID, $communeID, $email,
                    $profilePicturePathToUpdate,
                    $coverPicturePathToUpdate, // NEW
                    $userId
                );
                
                if ($stmt->execute()) {
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating profile: ' . $stmt->error;
                    $messageType = 'error';
                    error_log("Profile update MySQLi Error: " . $stmt->error);
                    // Clean up newly uploaded files if DB insert fails
                    if (!empty($_FILES['profile_picture']['name']) && $profilePicturePathToUpdate && file_exists($profilePicturePathToUpdate) && $profilePicturePathToUpdate !== $current_user_pics['ProfilePicture']) unlink($profilePicturePathToUpdate);
                    if (!empty($_FILES['cover_picture']['name']) && $coverPicturePathToUpdate && file_exists($coverPicturePathToUpdate) && $coverPicturePathToUpdate !== $current_user_pics['CoverPicture']) unlink($coverPicturePathToUpdate); // NEW
                }
                $stmt->close();
            } else {
                $message = 'Database statement preparation error: ' . $conn->error;
                $messageType = 'error';
                error_log("Profile update preparation error: " . $conn->error);
            }
        }
    }
}

// Fetch user data after potential update (to reflect any changes made)
// And fetch related location names for display in the view tab
$stmt_user = $conn->prepare("
    SELECT    
        u.*,
        pr.name AS ProvinceName,
        d.name AS DistrictName,
        c.name AS CommuneName
    FROM User u
    LEFT JOIN provinces pr ON u.ProvinceID = pr.id
    LEFT JOIN districts d ON u.DistrictID = d.id
    LEFT JOIN communes c ON u.CommuneID = c.id
    WHERE u.ID = ?
");
if ($stmt_user) {
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();
    $stmt_user->close();
    if ($result_user) $result_user->free();
    
    if (!$user) {
        error_log("User data not found for session ID: " . $userId . " during display.");
        header("Location: logout.php");
        exit;
    }
} else {
    die("Error fetching user data for display: " . $conn->error);
}

// Prepare initial districts and communes for dynamic loading if user already has them selected
$initialDistricts = [];
if (!empty($user['ProvinceID'])) {
    $stmt_districts = $conn->prepare("SELECT id, name, khmer_name FROM districts WHERE province_id = ? ORDER BY name");
    if ($stmt_districts) {
        $stmt_districts->bind_param("i", $user['ProvinceID']);
        $stmt_districts->execute();
        $initialDistricts = $stmt_districts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_districts->close();
    }
}

$initialCommunes = [];
if (!empty($user['DistrictID'])) {
    $stmt_communes = $conn->prepare("SELECT id, name, khmer_name FROM communes WHERE district_id = ? ORDER BY name");
    if ($stmt_communes) {
        $stmt_communes->bind_param("i", $user['DistrictID']);
        $stmt_communes->execute();
        $initialCommunes = $stmt_communes->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_communes->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ISLAMKH</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* General styles */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f0f2f5; /* Light gray background */
            min-height: 100vh; 
            padding: 20px; 
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .main-wrapper {
            max-width: 1000px; 
            width: 100%;
            margin: 0 auto; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24); 
            overflow: hidden;
        }

        /* Removed Top Header styles as per request */
        /*
        .top-header {
            background-color: #4CAF50;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .top-header .logo {
            font-size: 28px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-header .logo i {
            font-size: 32px;
        }
        .top-header .menu-icon {
            font-size: 24px;
            cursor: pointer;
        }
        */

        /* Profile Header Area */
        .profile-section {
            background: white;
            padding: 0 20px 20px; /* Padding for content below header */
            position: relative; /* Needed for absolute positioning of avatar */
        }
        .profile-banner {
            position: relative;
            height: 180px; /* Adjusted height for banner */
            background-color: #a0a0a0; /* Default background if no cover */
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 70px; /* Space for profile picture to overlap */
            display: flex; /* For centering the icon when no image */
            align-items: center;
            justify-content: center;
            font-size: 80px; /* Size of the default icon */
            color: #e0e0e0; /* Color of the default icon */
            overflow: hidden; /* Ensure image fits */
        }
        .profile-banner img.cover-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: absolute; /* Ensures it covers the div */
            top: 0; left: 0;
        }
        .profile-banner .edit-banner {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
            z-index: 10; /* Ensure button is above image */
        }
        .profile-banner .edit-banner:hover {
            opacity: 1;
        }

        .profile-avatar-container {
            position: absolute;
            bottom: -60px; /* Overlap the banner */
            left: 20px;
            background: white;
            border-radius: 50%;
            padding: 5px; /* Border effect */
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 20; /* Ensure avatar is above banner */
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e0e0e0; /* Default avatar background */
            color: #888;
            font-size: 60px;
            position: relative; /* For positioning the edit icon */
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar .edit-avatar {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #4CAF50; /* Green from the image */
            color: white;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 21; /* Ensure edit icon is above avatar */
        }

        .profile-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: -30px; /* Adjust based on avatar overlap */
            padding-left: 160px; /* Space for avatar */
            padding-right: 20px;
        }
        .profile-info .name-id {
            text-align: left;
        }
        .profile-info .name-id .profile-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .profile-info .name-id .profile-id {
            font-size: 14px;
            color: #777;
        }
        .profile-info .edit-profile-btn {
            background-color: #f0f2f5;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }
        .profile-info .edit-profile-btn:hover {
            background-color: #e0e0e0;
        }

        /* Search and Filter Section */
        .search-filter-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-filter-section .search-input-group {
            flex-grow: 1;
            position: relative;
        }
        .search-filter-section .search-input-group input {
            width: 100%;
            padding: 10px 10px 10px 40px; /* Space for icon */
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }
        .search-filter-section .search-input-group .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .search-filter-section .filter-dropdown {
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: white;
            cursor: pointer;
            font-size: 15px;
            appearance: none; /* Remove default arrow */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23000%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.5H18.4c-5%200-9.3%201.8-13.2%206.5-3.9%203.9-6.5%208.7-6.5%2013.2s2.6%209.3%206.5%2013.2L138.6%20228.6c3.9%203.9%208.7%206.5%2013.2%206.5s9.3-2.6%2013.2-6.5L287%2096.1c3.9-3.9%206.5-8.7%206.5-13.2S290.9%2073.3%20287%2069.4z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px; /* Space for custom arrow */
        }

        /* Tab Navigation (View/Edit) */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-left: 20px;
            margin-right: 20px;
                    margin-top: 50px;
        }
        .tab-navigation .nav-item {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #555;
            transition: color 0.3s, border-bottom 0.3s;
            border-bottom: 3px solid transparent;
            
        }
        .tab-navigation .nav-item.active {
            color: #4CAF50; /* Green from the image */
            border-bottom-color: #4CAF50;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            padding: 20px;
        }
        .tab-content.active {
            display: block;
        }

        /* Form Grid Layout */
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 25px; 
            margin-bottom: 30px; 
        }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        .form-control { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #e0e0e0; 
            border-radius: 5px; 
            font-size: 16px; 
            transition: all 0.3s ease; 
            background: white; 
            box-sizing: border-box; /* Ensure padding doesn't increase width */
        }
        .form-control:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2); }

        /* Info Display Section */
        .info-item { 
            display: flex; 
            align-items: center; 
            padding: 15px 0; 
            border-bottom: 1px solid #f1f5f9; 
        }
        .info-item:last-child { border-bottom: none; }
        .info-icon { 
            width: 40px; height: 40px; border-radius: 50%; 
            background-color: #f0f2f5; 
            display: flex; align-items: center; justify-content: center; 
            color: #4CAF50; margin-right: 15px; font-size: 16px; 
        }
        .info-content { flex: 1; }
        .info-label { font-weight: 600; color: #333; margin-bottom: 2px; font-size: 14px; }
        .info-value { color: #555; font-size: 15px; }

        /* Buttons */
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            font-size: 15px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            justify-content: center;
        }
        .btn-primary { background-color: #4CAF50; color: white; }
        .btn-primary:hover { background-color: #45a049; }
        .btn-secondary { background-color: #f0f2f5; color: #333; border: 1px solid #ccc; }
        .btn-secondary:hover { background-color: #e0e0e0; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-danger:hover { background-color: #b91c1c; }

        /* Messages */
        .message { padding: 15px 20px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .button-group { display: flex; gap: 15px; justify-content: center; margin-top: 30px; }

        /* Picture Upload Group */
        .picture-upload-group { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        .picture-upload-group .form-control[type="file"] { 
            flex-grow: 1; 
            min-width: 200px;
            padding: 8px; /* Adjust padding for file input */
            border-color: #ccc;
        }
        .btn-remove-picture { 
            background-color: #f44336; 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: 5px; 
            cursor: pointer; 
            transition: background-color 0.3s ease; 
            font-size: 14px;
        }
        .btn-remove-picture:hover { background-color: #d32f2f; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .main-wrapper { border-radius: 0; box-shadow: none; }
            .profile-banner { height: 120px; margin-bottom: 50px; }
            .profile-avatar-container { left: 10px; bottom: -40px; }
            .profile-avatar { width: 80px; height: 80px; font-size: 40px; }
            .profile-avatar .edit-avatar { width: 30px; height: 30px; font-size: 14px; }
            .profile-info { 
                flex-direction: column; 
                align-items: flex-start; 
                padding-left: 10px; 
                padding-right: 10px;
            }
            .profile-info .name-id { text-align: center; width: 100%; margin-bottom: 15px; }
            .profile-info .profile-name { font-size: 20px; }
            .profile-info .edit-profile-btn { margin-left: auto; margin-right: auto; }
            .search-filter-section { flex-direction: column; align-items: stretch; margin: 10px; padding: 10px; }
            .search-filter-section .filter-dropdown { width: 100%; }
            .tab-navigation { margin-left: 10px; margin-right: 10px; }
            .tab-navigation .nav-item { flex: 1; text-align: center; padding: 10px; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .button-group { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="profile-section">
            <div class="profile-banner">
                <?php if (!empty($user['CoverPicture'])): ?>
                    <img class="cover-photo" id="currentCoverPic" src="<?php echo htmlspecialchars($user['CoverPicture']); ?>" alt="Cover Photo">
                <?php else: ?>
                    <div class="cover-photo default-cover" id="currentCoverPic">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
                <div class="edit-banner" onclick="showTab(event, 'edit'); document.getElementById('cover_picture').click();">
                    <i class="fas fa-camera"></i> Edit Cover Photo
                </div>
            </div>

            <div class="profile-avatar-container">
                <div class="profile-avatar">
                    <?php if (!empty($user['ProfilePicture'])): ?>
                        <img id="currentProfilePicDisplay" src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div id="currentProfilePicDisplay" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div class="edit-avatar" onclick="showTab(event, 'edit'); document.getElementById('profile_picture').click();">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
            </div>

            <div class="profile-info">
                <div class="name-id">
                    <div class="profile-name">
                        <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                    </div>
                    <div class="profile-id">
                        @p-<?php echo htmlspecialchars($user['ID']); ?>
                        <span style="margin-left: 15px; color: #4CAF50;"><i class="fas fa-check-circle"></i> Verified User</span>
                    </div>
                </div>
                <button class="edit-profile-btn" onclick="showTab(event, 'edit')">
                    <i class="fas fa-pencil-alt"></i> Edit Profile
                </button>
            </div>
        </div>

      


        <div class="tab-navigation">
            <div class="nav-item active" onclick="showTab(event, 'view')">
                <i class="fas fa-user"></i> View Profile
            </div>
            <div class="nav-item" onclick="showTab(event, 'edit')">
                <i class="fas fa-edit"></i> Edit Profile
            </div>
        </div>

        <div id="view-tab" class="tab-content active">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="form-grid">
                <div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-user"></i></div>
                        <div class="info-content">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['FirstName'] ?: 'Not provided'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-user"></i></div>
                        <div class="info-content">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['LastName'] ?: 'Not provided'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-content">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Email'] ?: 'Not provided'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-content">
                            <div class="info-label">Primary Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['PhoneNumber'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-birthday-cake"></i></div>
                        <div class="info-content">
                            <div class="info-label">Birthday</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Birthday'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-venus-mars"></i></div>
                        <div class="info-content">
                            <div class="info-label">Gender</div>
                            <div class="info-value">
                                <?php
                                    $genderName = 'Not specified';
                                    foreach ($genders as $g) {
                                        if ($g['ID'] == $user['GenderID']) {
                                            $genderName = htmlspecialchars($g['Name']);
                                            break;
                                        }
                                    }
                                    echo $genderName;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-building"></i></div>
                        <div class="info-content">
                            <div class="info-label">Company</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Company'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="info-content">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Address'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-content">
                            <div class="info-label">Phone Number 2</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['PhoneNumber2'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-content">
                            <div class="info-label">Phone Number 3</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['PhoneNumber3'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-city"></i></div>
                        <div class="info-content">
                            <div class="info-label">Province</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['ProvinceName'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-road"></i></div>
                        <div class="info-content">
                            <div class="info-label">District</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['DistrictName'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-house-user"></i></div>
                        <div class="info-content">
                            <div class="info-label">Commune</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['CommuneName'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button class="btn btn-primary" onclick="showTab(event, 'edit')">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div id="edit-tab" class="tab-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-group">
                    <label for="cover_picture">Cover Picture</label>
                    <div class="picture-upload-group">
                        <input type="file" id="cover_picture" name="cover_picture" class="form-control" accept="image/*" onchange="previewFile(this, 'currentCoverPic')">
                        <?php if (!empty($user['CoverPicture'])): ?>
                            <button type="button" class="btn-remove-picture" id="removeCoverPictureBtn" onclick="confirmRemovePicture('cover_picture_flag', 'currentCoverPic', event)">
                                Remove Cover
                            </button>
                            <input type="hidden" id="cover_picture_flag" name="remove_cover_picture" value="0">
                        <?php endif; ?>
                    </div>
                    <small style="color: #777; display: block; margin-top: 5px;">Max 5MB. Allowed types: JPG, JPEG, PNG, GIF.</small>
                </div>

                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <div class="picture-upload-group">
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*" onchange="previewFile(this, 'currentProfilePicDisplay')">
                        <?php if (!empty($user['ProfilePicture'])): ?>
                            <button type="button" class="btn-remove-picture" id="removeProfilePictureBtn" onclick="confirmRemovePicture('profile_picture_flag', 'currentProfilePicDisplay', event)">
                                Remove Profile
                            </button>
                            <input type="hidden" id="profile_picture_flag" name="remove_profile_picture" value="0">
                        <?php endif; ?>
                    </div>
                    <small style="color: #777; display: block; margin-top: 5px;">Max 2MB. Allowed types: JPG, JPEG, PNG, GIF.</small>
                </div>

                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['FirstName']); ?>" required maxlength="50">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['LastName']); ?>" required maxlength="50">
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['Email']); ?>" required maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Primary Phone Number</label>
                            <input type="tel" id="phone_number" name="phone_number" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['PhoneNumber']); ?>" required maxlength="10">
                        </div>
                        <div class="form-group">
                            <label for="phone_number2">Phone Number 2</label>
                            <input type="tel" id="phone_number2" name="phone_number2" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['PhoneNumber2']); ?>" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label for="phone_number3">Phone Number 3</label>
                            <input type="tel" id="phone_number3" name="phone_number3" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['PhoneNumber3']); ?>" maxlength="10">
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label for="gender_id">Gender</label>
                            <select id="gender_id" name="gender_id" class="form-control">
                                <option value="">Select Gender</option>
                                <?php foreach ($genders as $gender): ?>
                                    <option value="<?php echo $gender['ID']; ?>"    
                                            <?php echo ($user['GenderID'] == $gender['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($gender['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Birthday</label>
                            <input type="date" id="birthday" name="birthday" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['Birthday'] ? date('Y-m-d', strtotime($user['Birthday'])) : ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="company">Company</label>
                            <input type="text" id="company" name="company" class="form-control"    
                                    value="<?php echo htmlspecialchars($user['Company']); ?>" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" maxlength="255"><?php echo htmlspecialchars($user['Address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="province_id_select">Province</label>
                            <select id="province_id_select" name="province_id" class="form-control">
                                <option value="">Select Province</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo $province['id']; ?>"    
                                            <?php echo ($user['ProvinceID'] == $province['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="district_id_select">District</label>
                            <select id="district_id_select" name="district_id" class="form-control">
                                <option value="">Select District</option>
                                <?php    
                                // These options are initially rendered by PHP based on $initialDistricts
                                if (!empty($initialDistricts)) {
                                    foreach ($initialDistricts as $district) {
                                        echo '<option value="' . htmlspecialchars($district['id']) . '"';
                                        echo ($user['DistrictID'] == $district['id']) ? ' selected' : '';
                                        echo '>' . htmlspecialchars($district['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="commune_id_select">Commune</label>
                            <select id="commune_id_select" name="commune_id" class="form-control">
                                <option value="">Select Commune</option>
                                <?php    
                                // These options are initially rendered by PHP based on $initialCommunes
                                if (!empty($initialCommunes)) {
                                    foreach ($initialCommunes as $commune) {
                                        echo '<option value="' . htmlspecialchars($commune['id']) . '"';
                                        echo ($user['CommuneID'] == $commune['id']) ? ' selected' : '';
                                        echo '>' . htmlspecialchars($commune['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="showTab(event, 'view')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => { // Made the async function
            const messageElement = document.querySelector('.message');
            if (messageElement) {
                setTimeout(() => {
                    messageElement.style.opacity = '0';
                    setTimeout(() => messageElement.remove(), 500);
                }, 5000);
            }

            // --- Tab Switching Logic ---
            window.showTab = function(event, tabId) {
                event.preventDefault();
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.querySelectorAll('.nav-item').forEach(button => {
                    button.classList.remove('active');
                });

                document.getElementById(tabId + '-tab').classList.add('active');
                // Ensure the event target (the clicked tab button) becomes active
                if (event.currentTarget) { // Check if event.currentTarget exists
                    event.currentTarget.classList.add('active'); 
                } else { // Fallback for direct calls (like from edit buttons)
                    // Find the tab button associated with the tabId and activate it
                    document.querySelector(`.nav-item[onclick*='${tabId}']`).classList.add('active');
                }
            };

            // --- Dynamic Location Dropdowns ---
            const provinceSelect = document.getElementById('province_id_select');
            const districtSelect = document.getElementById('district_id_select');
            const communeSelect = document.getElementById('commune_id_select');

            async function loadLocations(type, parentId, targetSelect, selectedId) {
                targetSelect.innerHTML = `<option value="">Loading ${type}...</option>`;
                targetSelect.disabled = true;

                try {
                    const response = await fetch(`fetch_locations.php?type=${type}&parentId=${parentId}`);
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    const data = await response.json();

                    // Clear and add default option first
                    targetSelect.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1, -1)}</option>`; // Capitalize first letter and remove 's'
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            // Use name + khmer_name for display if khmer_name exists
                            option.textContent = item.name + (item.khmer_name ? ` (${item.khmer_name})` : '');
                            if (selectedId && item.id == selectedId) {
                                option.selected = true;
                            }
                            targetSelect.appendChild(option);
                        });
                        targetSelect.disabled = false;
                    } else {
                        targetSelect.innerHTML = `<option value="">No ${type.charAt(0).toUpperCase() + type.slice(1, -1)} found</option>`;
                    }
                } catch (error) {
                    console.error(`Error loading ${type}:`, error);
                    targetSelect.innerHTML = `<option value="">Error loading ${type}</option>`;
                } finally {
                    targetSelect.disabled = false;
                }
            }

            provinceSelect.addEventListener('change', function() {
                const selectedProvinceId = this.value;
                districtSelect.innerHTML = '<option value="">Select District</option>';
                districtSelect.disabled = true;
                communeSelect.innerHTML = '<option value="">Select Commune</option>';
                communeSelect.disabled = true;

                if (selectedProvinceId) {
                    loadLocations('districts', selectedProvinceId, districtSelect, null);
                }
            });

            districtSelect.addEventListener('change', function() {
                const selectedDistrictId = this.value;
                communeSelect.innerHTML = '<option value="">Select Commune</option>';
                communeSelect.disabled = true;

                if (selectedDistrictId) {
                    loadLocations('communes', selectedDistrictId, communeSelect, null);
                }
            });

            // Initial load of districts and communes if user already has values
            const initialProvinceId = <?php echo json_encode($user['ProvinceID']); ?>;
            const initialDistrictId = <?php echo json_encode($user['DistrictID']); ?>;
            const initialCommuneId = <?php echo json_encode($user['CommuneID']); ?>;

            if (initialProvinceId) {
                // Await the district loading before attempting to load communes
                await loadLocations('districts', initialProvinceId, districtSelect, initialDistrictId);
                if (initialDistrictId) {
                    await loadLocations('communes', initialDistrictId, communeSelect, initialCommuneId);
                }
            }

            // Function to handle image preview
            window.previewFile = function(input, displayElementId) {
                const file = input.files[0];
                const displayElement = document.getElementById(displayElementId);
                const reader = new FileReader();

                reader.onload = function(e) {
                    if (displayElementId === 'currentProfilePicDisplay') {
                        // If it's a profile picture, replace the existing content with an img tag
                        if (displayElement.tagName === 'DIV') {
                            displayElement.innerHTML = ''; // Clear the existing icon
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            displayElement.appendChild(img);
                        } else if (displayElement.tagName === 'IMG') {
                            displayElement.src = e.target.result;
                        }
                    } else if (displayElementId === 'currentCoverPic') {
                        // If it's a cover picture, set background image
                        displayElement.style.backgroundImage = `url(${e.target.result})`;
                        displayElement.classList.remove('default-cover'); // Remove default icon style class
                        displayElement.innerHTML = ''; // Remove any default icon
                        // Also remove inline styles that set default background/font if they were applied
                        displayElement.style.backgroundColor = '';
                        displayElement.style.fontSize = '';
                        displayElement.style.color = '';
                    }
                    // Show the "Remove" button if it was hidden
                    const removeButtonId = (displayElementId === 'currentProfilePicDisplay') ? 'removeProfilePictureBtn' : 'removeCoverPictureBtn';
                    const removeButton = document.getElementById(removeButtonId);
                    if (removeButton) {
                        removeButton.style.display = 'inline-flex'; 
                    }
                };

                if (file) {
                    reader.readAsDataURL(file);
                }
            };

            // Function to handle profile/cover picture removal
            window.confirmRemovePicture = function(flagId, displayElementId, event) {
                event.preventDefault(); // Prevent form submission if button is type="submit"
                if (confirm('Are you sure you want to remove this picture? This change will be saved when you click "Save Changes".')) {
                    document.getElementById(flagId).value = '1';
                    const displayElement = document.getElementById(displayElementId);
                    
                    if (displayElementId === 'currentProfilePicDisplay') { // If it's the profile pic
                        if (displayElement.tagName === 'IMG') {
                            const parentDiv = displayElement.parentNode;
                            parentDiv.innerHTML = '<i class="fas fa-user"></i>';
                            // Re-apply original styles for default user icon if it was an img
                            parentDiv.style.width = '100%'; 
                            parentDiv.style.height = '100%';
                            parentDiv.style.display = 'flex';
                            parentDiv.style.alignItems = 'center';
                            parentDiv.style.justifyContent = 'center';
                        } else { // Already a div, just set content
                            displayElement.innerHTML = '<i class="fas fa-user"></i>';
                            displayElement.style.width = '100%'; 
                            displayElement.style.height = '100%';
                            displayElement.style.display = 'flex';
                            displayElement.style.alignItems = 'center';
                            displayElement.style.justifyContent = 'center';
                        }
                    } else if (displayElementId === 'currentCoverPic') { // If it's the cover pic
                        displayElement.style.backgroundImage = 'none'; // Remove background image
                        displayElement.style.backgroundColor = '#a0a0a0'; /* Set default background color */
                        displayElement.innerHTML = '<i class="fas fa-image"></i>'; // Show default icon
                        // Re-apply original styles for default image icon if it was an image
                        displayElement.style.display = 'flex';
                        displayElement.style.alignItems = 'center';
                        displayElement.style.justifyContent = 'center';
                        displayElement.style.fontSize = '80px';
                        displayElement.style.color = '#e0e0e0';
                        displayElement.classList.add('default-cover'); // Add the class back for consistent styling
                    }
                    
                    // Clear the file input associated with the picture
                    const fileInputId = (displayElementId === 'currentProfilePicDisplay') ? 'profile_picture' : 'cover_picture';
                    document.getElementById(fileInputId).value = ''; 

                    event.target.style.display = 'none'; // Hide the "Remove" button
                }
            };
        });
    </script>
</body>
</html>