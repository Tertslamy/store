<?php
// header.php
// This file contains the HTML, CSS, and PHP logic for the page header.
// It is designed to be included at the very top of other PHP pages.
// It now directly handles session and fetches user data and product categories from the database.

// Start the session if it hasn't been started yet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include mysqli database connection
// Assuming 'db.php' exists in the same directory and contains:
// $conn = new mysqli('localhost', 'username', 'password', 'database_name');
// if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
include "db.php";

// Initialize user data with defaults for guests or not-logged-in users
$user = [
    'ID' => 'Guest',
    'FirstName' => 'Guest',
    'LastName' => '',
    'ProfilePicture' => null
];
$userInitials = '?'; // Default initial for display

// Fetch user data from the database if a user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    // Using prepared statements to prevent SQL injection
    $stmt_header_user = $conn->prepare("SELECT ID, FirstName, LastName, ProfilePicture FROM User WHERE ID = ?");
    if ($stmt_header_user) {
        $stmt_header_user->bind_param("i", $userId);
        $stmt_header_user->execute();
        $result_header_user = $stmt_header_user->get_result();
        $fetchedUser = $result_header_user->fetch_assoc();
        $stmt_header_user->close();

        if ($fetchedUser) {
            $user = $fetchedUser; // Update $user with fetched data
            
            // Generate initials from first and last name
            $userInitials = '';
            if (!empty($user['FirstName'])) {
                $userInitials .= mb_substr($user['FirstName'], 0, 1, 'UTF-8');
            }
            if (!empty($user['LastName'])) {
                $userInitials .= mb_substr($user['LastName'], 0, 1, 'UTF-8');
            }
            $userInitials = mb_strtoupper($userInitials, 'UTF-8');

            // Fallback to first 2 characters of ID if no name initials
            if (empty($userInitials) && !empty($user['ID'])) {
                // Ensure ID is treated as a string for substr, though it's int in DB
                $userInitials = mb_substr((string)$user['ID'], 0, 2, 'UTF-8');
            }
        }
    } else {
        error_log("Header: Failed to prepare statement for user data: " . $conn->error);
    }
}

// PHP LOGIC FOR CATEGORIES
$productCategories = [];
// CORRECTED: Use 'categories' table instead of 'ProductCategory'
$stmt_categories = $conn->prepare("SELECT id AS ID, name AS Name FROM categories ORDER BY name ASC");
if ($stmt_categories) {
    $stmt_categories->execute();
    $result_categories = $stmt_categories->get_result();
    while ($row = $result_categories->fetch_assoc()) {
        $productCategories[] = $row;
    }
    $stmt_categories->close();
} else {
    error_log("Header: Failed to prepare statement for categories: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Header</title> <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Base styles for the header container */
        .khmer24-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background-color: white;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap; /* Allow items to wrap on smaller screens */
            gap: 10px; /* Space between items */
            position: relative; /* For dropdown positioning */
            z-index: 1000; /* Ensure header is on top */
            width: 100%; /* Ensure header spans full width */
            box-sizing: border-box; /* Include padding in width */
        }

        /* Logo section */
        .khmer24-header .logo-section {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0; /* Prevent shrinking */
        }
        .khmer24-header .logo-section .logo-text {
            font-family: 'Arial', sans-serif; /* A common sans-serif font */
            font-size: 28px;
            font-weight: bold;
            color: #333;
            line-height: 1; /* Adjust line height for better alignment */
            text-decoration: none; /* Remove underline from link */
        }
        .khmer24-header .logo-section .logo-underline {
            height: 3px;
            background-color: #007bff; /* Blue color from the image */
            width: 100%;
            margin-top: -5px; /* Adjust to sit under text */
            border-radius: 2px;
        }
        .khmer24-header .logo-section .flag-icon {
            font-size: 24px;
            color: #007bff; /* Blue color */
            margin-left: 5px;
        }

        /* Search and dropdown section */
        .khmer24-header .search-area {
            display: flex;
            flex-grow: 1; /* Allows search area to take available space */
            max-width: 600px; /* Max width for search bar */
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden; /* Ensures border-radius applies to children */
        }
        .khmer24-header .search-area .category-dropdown,
        .khmer24-header .search-area .subcategory-dropdown { /* Added subcategory dropdown */
            padding: 10px 15px;
            border: none;
            border-right: 1px solid #ccc;
            background-color: #f0f0f0;
            font-size: 15px;
            cursor: pointer;
            appearance: none; /* Remove default dropdown arrow */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23000%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.5H18.4c-5%200-9.3%201.8-13.2%206.5-3.9%203.9-6.5%208.7-6.5%2013.2s2.6%209.3%206.5%2013.2L138.6%20228.6c3.9%203.9%208.7%206.5%2013.2%206.5s9.3-2.6%2013.2-6.5L287%2096.1c3.9-3.9%206.5-8.7%206.5-13.2S290.9%2073.3%20287%2069.4z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px; /* Space for custom arrow */
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
            outline: none;
        }
        /* Specific styling for the subcategory dropdown to remove left radius if it's in the middle */
        .khmer24-header .search-area .subcategory-dropdown {
            border-radius: 0; /* No radius on left/right if it's between two elements */
        }
        .khmer24-header .search-area .search-input {
            flex-grow: 1;
            padding: 10px 15px;
            border: none;
            font-size: 15px;
            outline: none;
        }
        .khmer24-header .search-area .search-button {
            background-color: white; /* Background matches input */
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 18px;
            color: #777;
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
            transition: color 0.2s ease;
        }
        .khmer24-header .search-area .search-button:hover {
            color: #333;
        }

        /* Right-side container for icons, user profile, and post ad button */
        .khmer24-header .right-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-left: 20px; /* Space from search bar */
            flex-shrink: 0;
        }

        /* General icon buttons */
        .khmer24-header .right-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .khmer24-header .right-icons .icon-button {
            background: none;
            border: none;
            font-size: 24px;
            color: #777;
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 5px; /* Add padding for easier click */
            border-radius: 50%; /* Make it circular */
        }
        .khmer24-header .right-icons .icon-button:hover {
            color: #333;
            background-color: #f0f0f0;
        }

        /* Post Ad Button */
        .khmer24-header .post-ad-button {
            background-color: #FFA500; /* Orange color from the image */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s ease;
            flex-shrink: 0;
        }
        .khmer24-header .post-ad-button:hover {
            background-color: #FF8C00; /* Darker orange on hover */
        }

        /* User Profile Dropdown */
        .user-profile-dropdown-container {
            position: relative;
            display: flex; /* To align the toggle button */
            align-items: center;
            height: 40px; /* Match height of other icons/buttons */
        }

        .user-profile-toggle {
            background: none;
            border: none;
            padding: 0; /* Remove default button padding */
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; /* Fixed size for the toggle button */
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-profile-toggle img.user-profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        /* Styles for initials inside the small toggle circle */
        .user-profile-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: #e0e0e0; /* Default background for initials */
            color: #555; /* Color for initials */
            font-size: 18px; /* Font size for initials */
            font-weight: bold;
            border-radius: 50%;
        }

        .user-profile-menu {
            display: none;
            position: absolute;
            top: 100%; /* Position below the toggle button */
            right: 0;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            min-width: 250px;
            z-index: 100; /* Ensure it's above other content */
            padding: 15px;
            animation: fadeIn 0.2s ease-out;
        }

        .user-profile-menu.active {
            display: block;
        }

        .user-menu-header {
            display: flex;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 15px;
            text-decoration: none; /* Remove underline for the link */
            color: inherit; /* Inherit color */
            cursor: pointer; /* Indicate it's clickable */
        }

        .user-profile-pic-large {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .user-profile-pic-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        /* Styles for initials inside the large menu header circle */
        .user-profile-initials-large {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: #e0e0e0; /* Default background for initials */
            color: #555; /* Color for initials */
            font-size: 30px; /* Font size for initials */
            font-weight: bold;
            border-radius: 50%;
        }

        .user-menu-info {
            overflow: hidden; /* Hide overflow for long names */
        }
        .user-menu-info .user-menu-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            white-space: nowrap; /* Prevent name from wrapping */
            overflow: hidden;
            text-overflow: ellipsis; /* Add ellipsis if name is too long */
        }

        .user-menu-info .user-menu-id {
            font-size: 14px;
            color: #777;
        }

        .user-menu-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-menu-links li {
            margin-bottom: 8px;
        }

        .user-menu-links li:last-child {
            margin-bottom: 0;
        }

        .user-menu-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            text-decoration: none;
            color: #555;
            font-size: 16px;
            border-radius: 5px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .user-menu-links a:hover {
            background-color: #f0f0f0;
            color: #007bff;
        }
        .user-menu-links a i {
            width: 20px; /* Align icons */
            text-align: center;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 900px) {
            .khmer24-header {
                flex-direction: column; /* Stack items vertically */
                align-items: flex-start; /* Align items to the left */
                gap: 15px;
            }
            .khmer24-header .logo-section {
                width: 100%; /* Full width logo */
                justify-content: center; /* Center logo */
            }
            .khmer24-header .search-area {
                max-width: 100%; /* Allow search bar to take full width */
                width: 100%;
                order: 3; /* Move search bar to a new line */
                margin-top: 5px;
                flex-wrap: wrap; /* Allow dropdowns to wrap */
            }
            .khmer24-header .search-area .category-dropdown,
            .khmer24-header .search-area .subcategory-dropdown { /* Added subcategory dropdown */
                width: calc(50% - 5px); /* Two dropdowns side-by-side */
                margin-bottom: 10px; /* Space below dropdowns */
                border-radius: 5px; /* Re-apply border radius for individual elements */
                border-right: 1px solid #ccc; /* Keep right border */
            }
            .khmer24-header .search-area .category-dropdown {
                border-top-right-radius: 0; /* Remove right radius for first dropdown */
                border-bottom-right-radius: 0;
            }
            .khmer24-header .search-area .subcategory-dropdown {
                border-top-left-radius: 0; /* Remove left radius for second dropdown */
                border-bottom-left-radius: 0;
            }
            .khmer24-header .search-area .search-input {
                width: 100%; /* Search input takes full width */
                border-radius: 5px; /* Apply radius */
                margin-bottom: 10px;
            }
            .khmer24-header .search-area .search-button {
                width: 100%;
                border-radius: 5px;
            }
            .khmer24-header .right-section {
                width: 100%;
                justify-content: space-between; /* Distribute icons and button */
                margin-left: 0; /* Remove left margin */
                order: 2; /* Place icons/user/button before search on mobile */
            }
            .khmer24-header .right-icons {
                gap: 15px;
            }
            .khmer24-header .post-ad-button {
                padding: 8px 15px; /* Smaller padding */
                font-size: 14px; /* Smaller font */
            }

            /* Mobile specific for user profile menu (full screen overlay) */
            .user-profile-menu {
                position: fixed; /* Fixed position for full screen overlay */
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
                box-shadow: none;
                padding: 20px;
                background-color: white; /* Full white background */
                overflow-y: auto; /* Allow scrolling if content is long */
                display: flex;
                flex-direction: column;
                justify-content: flex-start; /* Align content to the top */
                align-items: center; /* Center content horizontally */
                transform: translateX(100%); /* Start off-screen to the right */
                transition: transform 0.3s ease-out;
            }
            .user-profile-menu.active {
                transform: translateX(0); /* Slide in from right */
            }
            .user-menu-header {
                width: 100%; /* Full width header in menu */
                flex-direction: column;
                text-align: center;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .user-profile-pic-large {
                margin-right: 0;
                margin-bottom: 15px;
            }
            .user-menu-info {
                text-align: center; /* Center text in mobile menu header */
            }
            .user-menu-links {
                width: 100%;
                max-width: 300px; /* Limit width of links for better readability */
            }
            .user-menu-links a {
                justify-content: center; /* Center links */
            }
        }

        @media (max-width: 600px) {
            .khmer24-header .logo-section .logo-text {
                font-size: 20px;
            }

            .khmer24-header .post-ad-button {
                padding: 6px 10px;
                font-size: 13px;
            }

            .khmer24-header .right-icons .icon-button {
                font-size: 20px;
            }

            .user-menu-info .user-menu-name {
                font-size: 16px;
            }

            .user-menu-info .user-menu-id {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <header class="khmer24-header">
        <div class="logo-section">
            <a href="/" style="text-decoration: none; color: inherit; display: flex; flex-direction: column;">
                <span class="logo-text">khmer24 <i class="fas fa-globe flag-icon"></i></span> <div class="logo-underline"></div>
            </a>
        </div>

        <div class="search-area">
            <select class="category-dropdown" id="mainCategoryDropdown">
                <option value="">ទាំងអស់ប្រភេទ</option> <?php foreach ($productCategories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['ID']); ?>">
                        <?php echo htmlspecialchars($category['Name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select class="subcategory-dropdown" id="subCategoryDropdown" disabled>
                <option value="">ជ្រើសរើសប្រភេទរង</option>
            </select>

            <input type="text" class="search-input" placeholder="ស្វែងរកទំនិញនៅទីនេះ..." /> <button class="search-button">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="right-section">
            <div class="right-icons">
                <button class="icon-button">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="icon-button">
                    <i class="fas fa-comment-dots"></i>
                </button>
            </div>

            <div class="user-profile-dropdown-container">
                <button class="user-profile-toggle" id="userProfileToggle">
                    <?php if (!empty($user['ProfilePicture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" alt="Profile" class="user-profile-pic">
                    <?php else: ?>
                        <div class="user-profile-initials">
                            <?php echo htmlspecialchars($userInitials); ?>
                        </div>
                    <?php endif; ?>
                </button>
                <div class="user-profile-menu" id="userProfileMenu">
                    <a href="my_profile.php" class="user-menu-header"> <div class="user-profile-pic-large">
                        <?php if (!empty($user['ProfilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="user-profile-initials-large">
                                <?php echo htmlspecialchars($userInitials); ?>
                            </div>
                        <?php endif; ?>
                        </div>
                        <div class="user-menu-info">
                            <div class="user-menu-name"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>
                            <div class="user-menu-id">@p-<?php echo htmlspecialchars($user['ID']); ?></div>
                        </div>
                    </a>
                    <ul class="user-menu-links">
                        <li><a href="my_profile.php"><i class="fas fa-user"></i> View Profile</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>

            <button class="post-ad-button">
                <i class="fas fa-camera"></i> ដាក់លក់ </button>
        </div>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userProfileToggle = document.getElementById('userProfileToggle');
            const userProfileMenu = document.getElementById('userProfileMenu');
            const mainCategoryDropdown = document.getElementById('mainCategoryDropdown');
            const subCategoryDropdown = document.getElementById('subCategoryDropdown');

            // User Profile Dropdown Logic
            if (userProfileToggle && userProfileMenu) {
                userProfileToggle.addEventListener('click', (event) => {
                    event.stopPropagation(); // Prevent click from immediately closing the menu
                    userProfileMenu.classList.toggle('active');
                });

                // Close the menu if clicked outside
                document.addEventListener('click', (event) => {
                    if (userProfileMenu.classList.contains('active') && !userProfileMenu.contains(event.target) && !userProfileToggle.contains(event.target)) {
                        userProfileMenu.classList.remove('active');
                    }
                });
            }

            // Product SubCategory Dropdown Logic
            if (mainCategoryDropdown && subCategoryDropdown) {
                mainCategoryDropdown.addEventListener('change', async () => {
                    const selectedCategoryId = mainCategoryDropdown.value;
                    subCategoryDropdown.innerHTML = '<option value="">ជ្រើសរើសប្រភេទរង</option>'; // Reset subcategory dropdown
                    subCategoryDropdown.disabled = true; // Disable until subcategories are loaded or if no category selected

                    if (selectedCategoryId) {
                        try {
                            const response = await fetch(`fetch_subcategories.php?category_id=${selectedCategoryId}`);
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            const subcategories = await response.json();

                            if (subcategories.length > 0) {
                                subcategories.forEach(subcat => {
                                    const option = document.createElement('option');
                                    option.value = subcat.ID;
                                    option.textContent = subcat.Name;
                                    subCategoryDropdown.appendChild(option);
                                });
                                subCategoryDropdown.disabled = false; // Enable if subcategories are loaded
                            } else {
                                const option = document.createElement('option');
                                option.value = "";
                                option.textContent = "គ្មានប្រភេទរង"; // "No Subcategories" in Khmer
                                subCategoryDropdown.appendChild(option);
                            }
                        } catch (error) {
                            console.error('Error fetching subcategories:', error);
                            const option = document.createElement('option');
                            option.value = "";
                            option.textContent = "បរាជ័យក្នុងការផ្ទុក"; // "Failed to load" in Khmer
                            subCategoryDropdown.appendChild(option);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>