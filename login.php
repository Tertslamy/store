<?php
// login.php
session_start(); // Start the session to store user info
include 'db.php'; // Include your database connection file (mysqli)

$error = '';

if (isset($_POST['submit'])) {
    $phoneNumber = trim($_POST['PhoneNumber']);
    $password = $_POST['Password'];

    // Basic validation
    if (empty($phoneNumber) || empty($password)) {
        $error = 'Phone Number and Password are required.';
    } else {
        // Prepare and execute the query to fetch user by phone number
        // Ensure 'Password' column in your 'User' table stores hashed passwords.
        // Also ensure 'IsActive' is a boolean or tinyint (1 for active, 0 for inactive).
        $stmt = $conn->prepare("SELECT ID, FirstName, LastName, Password, IsActive FROM `User` WHERE PhoneNumber = ?");
        if ($stmt) {
            $stmt->bind_param("s", $phoneNumber);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password against the hashed password stored in the database
                if (password_verify($password, $user['Password'])) {
                    // Check if account is active
                    if ($user['IsActive'] == 1) {
                        // Login successful, store user info in session
                        // Regenerate session ID to prevent session fixation attacks
                        session_regenerate_id(true); 
                        
                        $_SESSION['user_id'] = $user['ID'];
                        $_SESSION['user_name'] = $user['FirstName'] . ' ' . $user['LastName'];
                        $_SESSION['logged_in'] = true;

                        // Redirect to my_profile.php (ensure this file exists and handles logged-in users)
                        header("Location: my_profile.php");
                        exit;
                    } else {
                        $error = 'Your account is inactive. Please contact support.';
                    }
                } else {
                    // Generic error message for security (don't reveal if username or password was wrong)
                    $error = 'Invalid phone number or password.';
                }
            } else {
                // Generic error message for security
                $error = 'Invalid phone number or password.';
            }
            $stmt->close();
        } else {
            // This error indicates a problem with the SQL query preparation itself
            $error = 'Database query error: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Khmer24</title>
    <style>
        /* Re-use the CSS from your register.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e5e7eb;
        }

        .login-title {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .required {
            color: #ef4444;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background-color: white;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #6b7280;
            font-size: 18px;
        }

        .password-toggle:hover {
            color: #374151;
        }

        .submit-btn {
            width: 100%;
            background: #f97316;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .submit-btn:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            color: #6b7280;
            font-size: 14px;
        }

        .social-login {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            transition: all 0.3s ease;
        }

        .facebook-btn {
            background: #1877f2;
        }

        .google-btn {
            background: #db4437;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .register-link p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .register-btn {
            width: 100%;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .register-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Login to your account</h1>
        
        <?php if (isset($error) && !empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="PhoneNumber" class="form-label">Phone Number <span class="required">*</span></label>
                <input type="tel" id="PhoneNumber" name="PhoneNumber" class="form-input" required 
                        value="<?php echo isset($_POST['PhoneNumber']) ? htmlspecialchars($_POST['PhoneNumber']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="Password" class="form-label">Password <span class="required">*</span></label>
                <div class="password-container">
                    <input type="password" id="Password" name="Password" class="form-input" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('Password')">👁️</button>
                </div>
            </div>
            
            <button type="submit" name="submit" class="submit-btn">Login</button>
        </form>
        
        <div class="divider">Or</div>
        
        <div class="social-login">
            <button class="social-btn facebook-btn" onclick="facebookLogin()">f</button>
            <button class="social-btn google-btn" onclick="googleLogin()">G</button>
        </div>
        
        <div class="divider">Or</div>
        
        <div class="register-link">
            <p>Don't have an account?</p>
            <a href="register.php" class="register-btn">Register here</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🙈';
            } else {
                field.type = 'password';
                toggle.textContent = '👁️';
            }
        }
        
        function facebookLogin() {
            alert('Facebook login integration would go here');
            // In a real application, this would redirect to Facebook's OAuth page
            // or trigger a popup for Facebook login.
        }
        
        function googleLogin() {
            alert('Google login integration would go here');
            // In a real application, this would redirect to Google's OAuth page
            // or trigger a popup for Google login.
        }
        
        // This JavaScript ensures that only digits are entered into the phone number field.
        // It's client-side formatting and does not affect the backend validation.
        document.getElementById('PhoneNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
            e.target.value = value;
        });
    </script>
</body>
</html>