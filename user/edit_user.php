<?php
// edit_user.php
session_start(); // Start the session

// FOR DEBUGGING: Display errors directly on the page (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your mysqli database connection file
include 'db.php'; 

// --- Authorization Check (IMPORTANT for an admin page!) ---
// You must implement your own logic to ensure only authorized users (e.g., admins) can access this page.
// Example:
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     header("Location: login.php"); // Redirect to login page
//     exit;
// }

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Get user ID from URL
$user = null; // Initialize user data array
$message = '';
$messageType = '';

// If no user ID is provided, or it's invalid, redirect or show an error
if ($userId <= 0) {
    die("No user ID provided for editing. Please go back to the user list.");
}

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

// --- Handle Form Submission for User Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // Sanitize and trim inputs, applying database column length limits
    $firstName = substr(trim($_POST['first_name']), 0, 50); // varchar(50)
    $lastName = substr(trim($_POST['last_name']), 0, 50);   // varchar(50)
    
    $genderID = !empty($_POST['gender_id']) ? (int)$_POST['gender_id'] : NULL; // int, nullable
    $birthday = empty(trim($_POST['birthday'])) ? NULL : trim($_POST['birthday']); // datetime, nullable

    $company = substr(trim($_POST['company']), 0, 100) ?: NULL; // varchar(100)

    $phoneNumber = substr(trim($_POST['phone_number']), 0, 10); // varchar(10)
    $phoneNumber2 = substr(trim($_POST['phone_number2']), 0, 10) ?: NULL; // varchar(10), nullable
    $phoneNumber3 = substr(trim($_POST['phone_number3']), 0, 10) ?: NULL; // varchar(10), nullable

    $address = substr(trim($_POST['address']), 0, 255) ?: NULL; // varchar(255), nullable
    
    // Use 'province_id_select', 'district_id_select', 'commune_id_select' as names from the form
    $provinceID = !empty($_POST['province_id_select']) ? (int)$_POST['province_id_select'] : NULL; // int, nullable
    $districtID = !empty($_POST['district_id_select']) ? (int)$_POST['district_id_select'] : NULL; // int, nullable
    $communeID = !empty($_POST['commune_id_select']) ? (int)$_POST['commune_id_select'] : NULL; // int, nullable
    
    $email = substr(trim($_POST['email']), 0, 150); // varchar(150)
    
    $userName = substr(trim($_POST['username']), 0, 50) ?: NULL; // varchar(50), nullable

    // Basic server-side validation
    if (empty($firstName)) {
        $message = 'First Name is required.';
        $messageType = 'error';
    } elseif (empty($lastName)) {
        $message = 'Last Name is required.';
        $messageType = 'error';
    } elseif (empty($phoneNumber)) {
        $message = 'Primary Phone Number is required.';
        $messageType = 'error';
    } elseif (strlen($phoneNumber) > 10) { // Enforce varchar(10)
        $message = 'Primary Phone Number must be 10 digits or less.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $messageType = 'error';
    } else {
        // --- Profile Picture Upload/Removal Handling ---
        // Fetch current profile picture path from DB *before* potential overwrite
        $current_profile_picture_path = NULL;
        $stmt_current_pic = $conn->prepare("SELECT ProfilePicture FROM User WHERE ID = ?");
        if ($stmt_current_pic) {
            $stmt_current_pic->bind_param("i", $userId);
            $stmt_current_pic->execute();
            $current_pic_result = $stmt_current_pic->get_result();
            if ($current_pic_data = $current_pic_result->fetch_assoc()) {
                $current_profile_picture_path = $current_pic_data['ProfilePicture'];
            }
            $stmt_current_pic->close();
            if ($current_pic_result) $current_pic_result->free();
        } else {
            error_log("Error preparing current profile picture fetch: " . $conn->error);
            $message = 'Error retrieving current profile picture status.';
            $messageType = 'error';
        }
        
        $profilePicturePathToUpdate = $current_profile_picture_path; // Default to existing path

        // Only proceed with file operations if no initial DB error
        if (empty($message)) {
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileSize = $file['size'];
                $fileError = $file['error'];

                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
                $maxFileSize = 2 * 1024 * 1024; // 2MB

                if (in_array($fileExt, $allowedExtensions)) {
                    if ($fileError === 0) {
                        if ($fileSize < $maxFileSize) {
                            $uniqueFileName = uniqid('profile_', true) . '.' . $fileExt;
                            $uploadDir = 'uploads/profile_pictures/'; // This directory must exist and be writable!
                            
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true); 
                            }

                            $fileDestination = $uploadDir . $uniqueFileName;
                            
                            if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                // Delete old profile picture if it exists and is different from the new one
                                if (!empty($current_profile_picture_path) && file_exists($current_profile_picture_path) && $current_profile_picture_path !== $fileDestination) {
                                    unlink($current_profile_picture_path);
                                }
                                $profilePicturePathToUpdate = $fileDestination; // Update path for DB
                            } else {
                                $message = 'Failed to upload new profile picture.';
                                $messageType = 'error';
                            }
                        } else {
                            $message = 'File size is too large. Max 2MB allowed.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Error during file upload. Error code: ' . $fileError;
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.';
                    $messageType = 'error';
                }
            } elseif (isset($_POST['remove_picture']) && $_POST['remove_picture'] == '1') {
                // If 'remove_picture' flag is set to 1
                if (!empty($current_profile_picture_path) && file_exists($current_profile_picture_path)) {
                    unlink($current_profile_picture_path); // Delete the actual file
                }
                $profilePicturePathToUpdate = NULL; // Set path in DB to NULL
            }
        }
        // --- End Profile Picture Handling ---

        // Only proceed with database update if no validation or file upload errors occurred so far
        if (empty($message)) {
            // Check for Phone Number and Username uniqueness if they are changed
            $isPhoneNumberTaken = false;
            $isUsernameTaken = false;

            // Check if primary phone number is taken by another user
            $stmt_check_phone = $conn->prepare("SELECT ID FROM User WHERE PhoneNumber = ? AND ID != ?");
            if ($stmt_check_phone) {
                $stmt_check_phone->bind_param("si", $phoneNumber, $userId);
                $stmt_check_phone->execute();
                $check_phone_result = $stmt_check_phone->get_result();
                if ($check_phone_result->num_rows > 0) {
                    $isPhoneNumberTaken = true;
                    $message = 'Primary Phone Number is already taken by another user.';
                    $messageType = 'error';
                }
                $stmt_check_phone->close();
                if ($check_phone_result) $check_phone_result->free();
            } else {
                error_log("Failed to prepare phone number check statement: " . $conn->error);
                $message = 'Database error during phone number check.';
                $messageType = 'error';
            }

            // Check if username is taken by another user (only if username is provided)
            if (empty($message) && !empty($userName)) {
                $stmt_check_username = $conn->prepare("SELECT ID FROM User WHERE UserName = ? AND ID != ?");
                if ($stmt_check_username) {
                    $stmt_check_username->bind_param("si", $userName, $userId);
                    $stmt_check_username->execute();
                    $check_username_result = $stmt_check_username->get_result();
                    if ($check_username_result->num_rows > 0) {
                        $isUsernameTaken = true;
                        $message = 'Username is already taken by another user.';
                        $messageType = 'error';
                    }
                    $stmt_check_username->close();
                    if ($check_username_result) $check_username_result->free();
                } else {
                    error_log("Failed to prepare username check statement: " . $conn->error);
                    $message = 'Database error during username check.';
                    $messageType = 'error';
                }
            }

            if (empty($message)) { // Proceed only if all checks pass
                $stmt = $conn->prepare("
                    UPDATE User SET 
                        FirstName = ?,
                        LastName = ?,
                        GenderID = ?,
                        Birthday = ?,
                        Company = ?,
                        PhoneNumber = ?,
                        PhoneNumber2 = ?,
                        PhoneNumber3 = ?,
                        Address = ?,
                        ProvinceID = ?,
                        DistrictID = ?,
                        CommuneID = ?,
                        Email = ?,
                        UserName = ?,
                        ProfilePicture = ?
                    WHERE ID = ?
                ");
                
                if ($stmt) {
                    // "ssissssssiiissi" - bind types for all parameters
                    // s: string, i: integer
                    $stmt->bind_param("ssissssssiiissi",
                        $firstName,
                        $lastName,
                        $genderID, // NULL allowed for int
                        $birthday, // NULL allowed for datetime
                        $company,  // NULL allowed for varchar
                        $phoneNumber,
                        $phoneNumber2, // NULL allowed for varchar
                        $phoneNumber3, // NULL allowed for varchar
                        $address,    // NULL allowed for varchar
                        $provinceID, // NULL allowed for int
                        $districtID, // NULL allowed for int
                        $communeID,  // NULL allowed for int
                        $email,
                        $userName,   // NULL allowed for varchar
                        $profilePicturePathToUpdate, // NULL allowed for varchar
                        $userId // for WHERE clause
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'User profile updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating user profile: ' . $stmt->error;
                        $messageType = 'error';
                        error_log("User profile update MySQLi Error: " . $stmt->error);
                        // If DB update failed after file upload, delete the newly uploaded file
                        if (!empty($_FILES['profile_picture']['name']) && $profilePicturePathToUpdate && file_exists($profilePicturePathToUpdate) && $profilePicturePathToUpdate !== $current_profile_picture_path) {
                            unlink($profilePicturePathToUpdate);
                        }
                    }
                    $stmt->close();
                } else {
                    $message = 'Database statement preparation error: ' . $conn->error;
                    $messageType = 'error';
                    error_log("User update preparation error: " . $conn->error);
                }
            }
        }
    }
}

// --- Fetch User Data for form pre-filling (ALWAYS run, even after POST) ---
// This ensures the form displays the latest data or pre-fills correctly on first load.
// Also fetch related location names for display in the view tab
$stmt_user = $conn->prepare("
    SELECT 
        u.*,
        p.name AS ProvinceName,
        d.name AS DistrictName,
        c.name AS CommuneName
    FROM User u
    LEFT JOIN provinces p ON u.ProvinceID = p.id
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
        die("User not found with ID: " . htmlspecialchars($userId) . ". Cannot edit non-existent user.");
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
    <title>Edit User Profile - <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --background-color: #f8fafc;
            --surface-color: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-surface: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .header {
            background: var(--gradient-surface);
            padding: 32px 40px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .header-title .subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--primary-dark);
        }

        .form-container {
            padding: 40px;
        }

        .message {
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            border: 1px solid transparent;
            animation: slideIn 0.3s ease-out;
        }

        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 32px;
            margin-bottom: 40px;
            padding: 32px;
            background: var(--gradient-surface);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
        }

        .profile-picture-container {
            position: relative;
            flex-shrink: 0;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--surface-color);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .profile-picture:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-xl);
        }

        .profile-picture-default {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--text-secondary);
            border: 4px solid var(--surface-color);
            box-shadow: var(--shadow-lg);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .profile-info p {
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 32px;
            margin-bottom: 40px;
        }

        .form-section {
            background: var(--surface-color);
            padding: 32px;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .form-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-section h3 i {
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--surface-color);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-control:disabled {
            background: var(--background-color);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .file-upload-container {
            position: relative;
            margin-bottom: 24px;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px;
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--background-color);
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }

        .file-upload-label i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .file-upload-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .file-upload-text {
            text-align: center;
        }

        .file-upload-text .main {
            font-weight: 500;
            color: var(--text-primary);
        }

        .file-upload-text .sub {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .button-group {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            min-width: 140px;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--surface-color);
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--background-color);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .remove-picture-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        .remove-picture-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: var(--border-radius);
            }

            .header {
                padding: 24px 20px;
            }

            .header-title h1 {
                font-size: 1.5rem;
            }

            .form-container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .form-section {
                padding: 20px;
            }

            .profile-section {
                flex-direction: column;
                text-align: center;
                padding: 24px;
            }

            .button-group {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                min-width: auto;
            }
        }

        .form-hint {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .required::after {
            content: ' *';
            color: var(--error-color);
        }
    </style>
</head>
<body>
    <div class="container">
       
            <div class="header-content">
                <div class="header-title">
                    <div>
                        <h1><i class="fas fa-user-edit"></i> Edit User Profile</h1>
                        <div class="subtitle">Manage user information and settings</div>
                    </div>
                </div>
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="user_list.php">Users</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Edit User</span>
                </div>
            </div>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php if ($messageType === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-section">
                <div class="profile-picture-container">
                    <?php if (!empty($user['ProfilePicture']) && file_exists($user['ProfilePicture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" 
                             alt="Profile Picture" class="profile-picture">
                        <button type="button" class="remove-picture-btn" onclick="removePicture()">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php else: ?>
                        <div class="profile-picture-default">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['Email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['PhoneNumber']); ?></p>
                    <?php if (!empty($user['Company'])): ?>
                        <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['Company']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user['ProvinceName'])): ?>
                        <p><i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($user['ProvinceName']); ?>
                            <?php if (!empty($user['DistrictName'])): ?>
                                , <?php echo htmlspecialchars($user['DistrictName']); ?>
                            <?php endif; ?>
                            <?php if (!empty($user['CommuneName'])): ?>
                                , <?php echo htmlspecialchars($user['CommuneName']); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" id="editUserForm">
                <div class="form-grid">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="form-group">
                            <label for="first_name" class="required">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['FirstName']); ?>" 
                                   maxlength="50" required>
                            <div class="form-hint">Maximum 50 characters</div>
                        </div>

                        <div class="form-group">
                            <label for="last_name" class="required">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['LastName']); ?>" 
                                   maxlength="50" required>
                            <div class="form-hint">Maximum 50 characters</div>
                        </div>

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
                                   value="<?php echo !empty($user['Birthday']) ? date('Y-m-d', strtotime($user['Birthday'])) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="company">Company</label>
                            <input type="text" id="company" name="company" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['Company']); ?>" 
                                   maxlength="100">
                            <div class="form-hint">Maximum 100 characters</div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-phone"></i> Contact Information</h3>
                        
                        <div class="form-group">
                            <label for="phone_number" class="required">Primary Phone Number</label>
                            <input type="tel" id="phone_number" name="phone_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['PhoneNumber']); ?>" 
                                   maxlength="10" required>
                            <div class="form-hint">Maximum 10 digits</div>
                        </div>

                        <div class="form-group">
                            <label for="phone_number2">Secondary Phone Number</label>
                            <input type="tel" id="phone_number2" name="phone_number2" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['PhoneNumber2']); ?>" 
                                   maxlength="10">
                            <div class="form-hint">Maximum 10 digits</div>
                        </div>

                        <div class="form-group">
                            <label for="phone_number3">Third Phone Number</label>
                            <input type="tel" id="phone_number3" name="phone_number3" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['PhoneNumber3']); ?>" 
                                   maxlength="10">
                            <div class="form-hint">Maximum 10 digits</div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['Email']); ?>" 
                                   maxlength="150" required>
                            <div class="form-hint">Maximum 150 characters</div>
                        </div>
                    </div>

                    <!-- Address Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                        
                        <div class="form-group">
                            <label for="address">Street Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      maxlength="255"><?php echo htmlspecialchars($user['Address']); ?></textarea>
                            <div class="form-hint">Maximum 255 characters</div>
                        </div>

                        <div class="form-group">
                            <label for="province_id_select">Province</label>
                            <select id="province_id_select" name="province_id_select" class="form-control">
                                <option value="">Select Province</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo $province['id']; ?>" 
                                            <?php echo ($user['ProvinceID'] == $province['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province['name']); ?>
                                        <?php if (!empty($province['khmer_name'])): ?>
                                            (<?php echo htmlspecialchars($province['khmer_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="district_id_select">District</label>
                            <select id="district_id_select" name="district_id_select" class="form-control">
                                <option value="">Select District</option>
                                <?php foreach ($initialDistricts as $district): ?>
                                    <option value="<?php echo $district['id']; ?>" 
                                            <?php echo ($user['DistrictID'] == $district['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($district['name']); ?>
                                        <?php if (!empty($district['khmer_name'])): ?>
                                            (<?php echo htmlspecialchars($district['khmer_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="commune_id_select">Commune</label>
                            <select id="commune_id_select" name="commune_id_select" class="form-control">
                                <option value="">Select Commune</option>
                                <?php foreach ($initialCommunes as $commune): ?>
                                    <option value="<?php echo $commune['id']; ?>" 
                                            <?php echo ($user['CommuneID'] == $commune['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($commune['name']); ?>
                                        <?php if (!empty($commune['khmer_name'])): ?>
                                            (<?php echo htmlspecialchars($commune['khmer_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-user-cog"></i> Account Information</h3>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['UserName']); ?>" 
                                   maxlength="50">
                            <div class="form-hint">Maximum 50 characters, leave blank to keep current</div>
                        </div>

                        <div class="form-group">
                            <label for="profile_picture">Profile Picture</label>
                            <div class="file-upload-container">
                                <label for="profile_picture" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div class="file-upload-text">
                                        <div class="main">Choose a new profile picture</div>
                                        <div class="sub">JPG, JPEG, PNG, GIF (Max 2MB)</div>
                                    </div>
                                </label>
                                <input type="file" id="profile_picture" name="profile_picture" 
                                       class="file-upload-input" accept="image/*">
                            </div>
                        </div>

                        <input type="hidden" id="remove_picture" name="remove_picture" value="0">
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <a href="user_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                    <a href="view_user.php?id=<?php echo $userId; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dynamic location loading
        document.getElementById('province_id_select').addEventListener('change', function() {
            const provinceId = this.value;
            const districtSelect = document.getElementById('district_id_select');
            const communeSelect = document.getElementById('commune_id_select');
            
            // Clear districts and communes
            districtSelect.innerHTML = '<option value="">Select District</option>';
            communeSelect.innerHTML = '<option value="">Select Commune</option>';
            
            if (provinceId) {
                districtSelect.classList.add('loading');
                fetch(`get_districts.php?province_id=${provinceId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district.id;
                            option.textContent = district.name + (district.khmer_name ? ` (${district.khmer_name})` : '');
                            districtSelect.appendChild(option);
                        });
                        districtSelect.classList.remove('loading');
                    })
                    .catch(error => {
                        console.error('Error loading districts:', error);
                        districtSelect.classList.remove('loading');
                    });
            }
        });

        document.getElementById('district_id_select').addEventListener('change', function() {
            const districtId = this.value;
            const communeSelect = document.getElementById('commune_id_select');
            
            // Clear communes
            communeSelect.innerHTML = '<option value="">Select Commune</option>';
            
            if (districtId) {
                communeSelect.classList.add('loading');
                fetch(`get_communes.php?district_id=${districtId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(commune => {
                            const option = document.createElement('option');
                            option.value = commune.id;
                            option.textContent = commune.name + (commune.khmer_name ? ` (${commune.khmer_name})` : '');
                            communeSelect.appendChild(option);
                        });
                        communeSelect.classList.remove('loading');
                    })
                    .catch(error => {
                        console.error('Error loading communes:', error);
                        communeSelect.classList.remove('loading');
                    });
            }
        });

        // File upload preview
        document.getElementById('profile_picture').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const profileContainer = document.querySelector('.profile-picture-container');
                    profileContainer.innerHTML = `
                        <img src="${e.target.result}" alt="Profile Picture" class="profile-picture">
                        <button type="button" class="remove-picture-btn" onclick="removePicture()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // Remove picture function
        function removePicture() {
            if (confirm('Are you sure you want to remove the profile picture?')) {
                document.getElementById('remove_picture').value = '1';
                document.getElementById('profile_picture').value = '';
                
                const profileContainer = document.querySelector('.profile-picture-container');
                profileContainer.innerHTML = `
                    <div class="profile-picture-default">
                        <i class="fas fa-user"></i>
                    </div>
                `;
            }
        }

        // Form validation
        document.getElementById('editUserForm').addEventListener('submit', function(event) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const phoneNumber = document.getElementById('phone_number').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!firstName) {
                alert('First Name is required.');
                event.preventDefault();
                return;
            }
            
            if (!lastName) {
                alert('Last Name is required.');
                event.preventDefault();
                return;
            }
            
            if (!phoneNumber) {
                alert('Primary Phone Number is required.');
                event.preventDefault();
                return;
            }
            
            if (phoneNumber.length > 10) {
                alert('Primary Phone Number must be 10 digits or less.');
                event.preventDefault();
                return;
            }
            
            if (!email) {
                alert('Email is required.');
                event.preventDefault();
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                event.preventDefault();
                return;
            }
        });

        // Auto-hide messages after 5 seconds
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>