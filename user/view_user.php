<?php
// view_user.php
ini_set('display_errors', 1); // FOR DEBUGGING: Display errors directly on the page
ini_set('display_startup_errors', 1); // FOR DEBUGGING: Display startup errors
error_reporting(E_ALL); // FOR DEBUGGING: Report all types of errors

session_start(); // Start the session to check login status

// Include your mysqli database connection file
// Make sure db.php establishes a mysqli connection and makes it available as $conn
include 'db.php'; 

// Optional: Implement robust authorization here.
// For example, only allow administrators or the user themselves to view this page.
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//     header("Location: login.php");
//     exit;
// }
// You might also add a check like:
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $_GET['id']) {
//         die("Unauthorized access."); // Or redirect to a forbidden page
//     }
// }

$user = null; // Initialize $user variable
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Get user ID from URL, cast to int

if ($userId > 0) {
    try {
        // Prepare statement to fetch user details with joined Gender and Province names
        // 'u.*' selects all columns from the User table
        // 'g.Name AS GenderName' gets the gender name and aliases it
        // 'p.Name AS ProvinceName' gets the province name and aliases it
        $stmt = $conn->prepare("
            SELECT 
                u.*, 
                g.Name AS GenderName, 
                p.Name AS ProvinceName
            FROM User u
            LEFT JOIN Gender g ON u.GenderID = g.ID
            LEFT JOIN Province p ON u.ProvinceID = p.ID
            WHERE u.ID = ?
        ");
        
        // Check if the statement preparation was successful
        if ($stmt) {
            // Bind the user ID parameter to the prepared statement
            $stmt->bind_param("i", $userId); // "i" indicates integer type
            
            // Execute the prepared statement
            $stmt->execute();
            
            // Get the result set from the executed statement
            $result = $stmt->get_result();
            
            // Fetch the user data as an associative array
            $user = $result->fetch_assoc();
            
            // Close the statement
            $stmt->close();
            
            // Free the result set (important for memory management)
            if ($result) $result->free();

            // If no user was found with the given ID, display a specific message
            if (!$user) {
                die("User not found with ID: " . htmlspecialchars($userId) . ". Please check if this ID exists in your database.");
            }

        } else {
            // Log the error and show a generic message
            error_log("Failed to prepare user details statement: " . $conn->error);
            die("Database Error: Failed to prepare statement. Please check your SQL query and table structure. MySQLi Error: " . $conn->error); // More detailed error for debugging
        }

    } catch (Exception $e) {
        // Catch any other unexpected errors during the process
        error_log("Error fetching user details: " . $e->getMessage());
        die("An unexpected error occurred: " . $e->getMessage() . ". Please check PHP error logs."); // More detailed error for debugging
    }
} else {
    // If no valid User ID was provided in the URL
    die("Invalid User ID provided. Please specify a valid user ID (e.g., view_user.php?id=1).");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User: <?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Basic reset and body styles */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            padding: 20px; 
            display: flex; 
            justify-content: center; 
            align-items: flex-start; /* Align content to the top */
        }
        
        /* Container for the user profile */
        .container { 
            max-width: 800px; 
            margin: 30px auto; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            padding: 40px; 
        }

        /* Page title */
        h1 { 
            text-align: center; 
            color: #007bff; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 15px; 
        }

        /* Profile Picture styles */
        .profile-picture-lg { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            object-fit: cover; /* Ensures image covers the area without distortion */
            border: 5px solid #eee; 
            margin: 0 auto 20px; 
            display: block; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
        }
        .profile-picture-lg-default { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            background-color: #ccc; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 60px; 
            color: #666; 
            margin: 0 auto 20px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
        }

        /* User details grid layout */
        .user-details { 
            display: grid; 
            grid-template-columns: 1fr 1fr; /* Two columns */
            gap: 20px; 
            margin-top: 30px; 
        }
        .detail-item { 
            padding: 10px 0; 
            border-bottom: 1px dashed #eee; 
        }
        .detail-item:last-child { 
            border-bottom: none; /* No border for the last item */
        }
        .detail-label { 
            font-weight: bold; 
            color: #555; 
            margin-bottom: 5px; 
            display: block; 
        }
        .detail-value { 
            color: #333; 
        }

        /* Back button style */
        .btn-back { 
            display: block; 
            width: fit-content; /* Adjusts width to content */
            margin: 30px auto 0; 
            padding: 10px 20px; 
            background-color: #007bff; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            text-align: center; 
            transition: background-color 0.3s ease; 
        }
        .btn-back:hover { 
            background-color: #0056b3; 
        }

        /* Status indicators */
        .status-active { 
            color: #28a745; 
            font-weight: bold; 
        }
        .status-inactive { 
            color: #dc3545; 
            font-weight: bold; 
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 600px) {
            .user-details {
                grid-template-columns: 1fr; /* Single column on small screens */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>User Profile</h1>
        
        <?php if (!empty($user['ProfilePicture'])): ?>
            <img src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" alt="Profile Picture" class="profile-picture-lg">
        <?php else: ?>
            <div class="profile-picture-lg-default"><i class="fas fa-user"></i></div>
        <?php endif; ?>

        <h2 style="text-align: center; margin-bottom: 20px; color: #333;"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></h2>

        <div class="user-details">
            <div class="detail-item">
                <span class="detail-label">User ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['ID']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Username:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['UserName'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Email'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Primary Phone:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['PhoneNumber'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone 2:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['PhoneNumber2'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone 3:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['PhoneNumber3'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Gender:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['GenderName'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Birthday:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Birthday'] ? date('M d, Y', strtotime($user['Birthday'])) : 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Company:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Company'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Address:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Address'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Province:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['ProvinceName'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">District ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['DistrictID'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Commune ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['CommuneID'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Latitude:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Latitude'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Longitude:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['Longitude'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Facebook Auth:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['FacebookAuth'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Google Auth:</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['GoogleAuth'] ?: 'N/A'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <?php if ($user['IsActive'] == 1): ?>
                        <span class="status-active">Active</span>
                    <?php else: ?>
                        <span class="status-inactive">Inactive</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <a href="list_user.php" class="btn-back">Back to User List</a>
    </div>
</body>
</html>