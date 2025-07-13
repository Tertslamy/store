<?php
// list_user.php
session_start();
include 'db.php'; // Include your mysqli database connection file

// Optional: Check if admin/authorized user (implement your own authorization logic)
// For simplicity, this example doesn't restrict access, but you should in production.
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     header("Location: login.php");
//     exit;
// }

$users = [];
$limit = 10; // Number of users per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$bind_params = [];
$bind_types = '';

if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $where_clause = " WHERE FirstName LIKE ? OR LastName LIKE ? OR PhoneNumber LIKE ? OR Email LIKE ? OR UserName LIKE ?";
    $bind_params = [$search_param, $search_param, $search_param, $search_param, $search_param];
    $bind_types = "sssss";
}

try {
    // Count total users for pagination
    $count_stmt = $conn->prepare("SELECT COUNT(ID) AS total FROM User" . $where_clause);
    if ($count_stmt) {
        if (!empty($bind_params)) {
            // Use call_user_func_array for bind_param with variable number of arguments
            call_user_func_array([$count_stmt, 'bind_param'], array_merge([$bind_types], $bind_params));
        }
        $count_stmt->execute();
        $total_users_result = $count_stmt->get_result()->fetch_assoc();
        $total_users = $total_users_result['total'];
        $count_stmt->close();
    } else {
        throw new Exception("Failed to prepare count statement: " . $conn->error);
    }

    $total_pages = ceil($total_users / $limit);

    // Fetch users with pagination and search
    $sql = "SELECT ID, FirstName, LastName, PhoneNumber, Email, UserName, IsActive, ProfilePicture FROM User" . $where_clause . " ORDER BY ID DESC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Create an array for parameters to pass to bind_param, including limit and offset
        $fetch_bind_params = $bind_params; // Start with search params if any
        $fetch_bind_params[] = $offset;
        $fetch_bind_params[] = $limit;
        $fetch_bind_types = $bind_types . "ii"; // Add types for limit and offset

        // Use call_user_func_array for dynamic bind_param
        call_user_func_array([$stmt, 'bind_param'], array_merge([$fetch_bind_types], $fetch_bind_params));
        
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        if ($result) $result->free();
    } else {
        throw new Exception("Failed to prepare user fetch statement: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Error fetching user list: " . $e->getMessage());
    die("An error occurred while loading users. Please try again later.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f4f7f6; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 30px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #007bff; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .search-bar { margin-bottom: 20px; text-align: center; }
        .search-bar input[type="text"] { width: 300px; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        .search-bar button { padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background-color 0.3s ease; }
        .search-bar button:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; }
        th { background-color: #007bff; color: white; font-weight: 600; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f1f1f1; }
        .profile-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #eee; vertical-align: middle; margin-right: 10px; }
        .profile-thumb-default { width: 40px; height: 40px; border-radius: 50%; background-color: #ccc; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; color: #666; vertical-align: middle; margin-right: 10px; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        .action-links a { margin-right: 10px; color: #007bff; text-decoration: none; }
        .action-links a:hover { text-decoration: underline; }
        .action-links .edit-btn { color: #ffc107; } /* Styling for edit link */
        .pagination { text-align: center; margin-top: 20px; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 16px; margin: 0 5px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #007bff; }
        .pagination a:hover:not(.active) { background-color: #f2f2f2; }
        .pagination span.active { background-color: #007bff; color: white; border: 1px solid #007bff; }
        .pagination span.disabled { color: #ccc; cursor: not-allowed; }
        .no-users { text-align: center; padding: 20px; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>User List</h1>

        <div class="search-bar">
            <form action="list_user.php" method="GET">
                <input type="text" name="search" placeholder="Search by name, phone, or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search_query)): ?>
                    <a href="list_user.php" style="margin-left: 10px; color: #dc3545; text-decoration: none;">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($users)): ?>
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone Number</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <?php if (!empty($user['ProfilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" alt="Profile Picture" class="profile-thumb">
                        <?php else: ?>
                            <div class="profile-thumb-default"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['ID']); ?></td>
                    <td><?php echo htmlspecialchars($user['FirstName']); ?></td>
                    <td><?php echo htmlspecialchars($user['LastName']); ?></td>
                    <td><?php echo htmlspecialchars($user['PhoneNumber']); ?></td>
                    <td><?php echo htmlspecialchars($user['Email'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['UserName'] ?: 'N/A'); ?></td>
                    <td>
                        <?php if ($user['IsActive'] == 1): ?>
                            <span class="status-active">Active</span>
                        <?php else: ?>
                            <span class="status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-links">
                        <a href="view_user.php?id=<?php echo htmlspecialchars($user['ID']); ?>">View</a>
                        <a href="edit_user.php?id=<?php echo htmlspecialchars($user['ID']); ?>" class="edit-btn">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">Previous</a>
            <?php else: ?>
                <span class="disabled">Previous</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">Next</a>
            <?php else: ?>
                <span class="disabled">Next</span>
            <?php endif; ?>
        </div>

        <?php else: ?>
            <p class="no-users">No users found.</p>
        <?php endif; ?>
    </div>
</body>
</html>