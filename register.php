<?php
include 'db.php';
$genders = $conn->query("SELECT * FROM Gender");

// Handle form submission
if (isset($_POST['submit'])) {
    $firstName = trim($_POST['FirstName']);
    $lastName = trim($_POST['LastName']);
    $genderID = $_POST['GenderID'];
    $phoneNumber = trim($_POST['PhoneNumber']); // This already handles non-digits if JS removes them
    $userName = trim($_POST['UserName']);
    $password = $_POST['Password'];
    $confirmPassword = $_POST['ConfirmPassword'];
    
    $error = '';
    $success = '';
    
    // Validation
    if (empty($firstName)) {
        $error = 'First Name is required';
    } elseif (empty($lastName)) {
        $error = 'Last Name is required';
    } elseif (empty($phoneNumber)) {
        $error = 'Phone Number is required';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if phone number already exists
        $check = $conn->prepare("SELECT ID FROM User WHERE PhoneNumber = ?");
        $check->bind_param("s", $phoneNumber);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Phone number already registered';
        } else {
            // Hash password for security
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO `User` 
                (FirstName, LastName, GenderID, PhoneNumber, UserName, `Password`, IsActive)
                VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssisss",
                $firstName,
                $lastName,
                $genderID,
                $phoneNumber, // Value here will be digits only due to JS
                $userName,
                $hashedPassword
            );
            
            if ($stmt->execute()) {
                $success = "User registered successfully!";
                // Clear form data for a fresh form after successful registration
                $_POST = array(); 
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Khmer24</title>
    <style>
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

        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e5e7eb;
        }

        .register-title {
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

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }

        .form-input:focus, .form-select:focus {
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

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .login-link p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .login-btn {
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

        .login-btn:hover {
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

        .success-message {
            background: #f0fdf4;
            color: #16a34a;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .register-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1 class="register-title">Register to post free ad</h1>
        
        <?php if (isset($error) && !empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success) && !empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="FirstName" class="form-label">First Name <span class="required">*</span></label>
                <input type="text" id="FirstName" name="FirstName" class="form-input" required 
                        value="<?php echo isset($_POST['FirstName']) ? htmlspecialchars($_POST['FirstName']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="LastName" class="form-label">Last Name <span class="required">*</span></label>
                <input type="text" id="LastName" name="LastName" class="form-input" required 
                        value="<?php echo isset($_POST['LastName']) ? htmlspecialchars($_POST['LastName']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="GenderID" class="form-label">Gender</label>
                <select id="GenderID" name="GenderID" class="form-select">
                    <option value="">-- Select Gender --</option>
                    <?php 
                    // Re-fetch genders for the form display (good practice to ensure fresh data)
                    $genders = $conn->query("SELECT * FROM Gender");
                    while ($g = $genders->fetch_assoc()): 
                    ?>
                        <option value="<?= $g['ID'] ?>" <?php echo (isset($_POST['GenderID']) && $_POST['GenderID'] == $g['ID']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($g['Name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="PhoneNumber" class="form-label">Phone Number <span class="required">*</span></label>
                <input type="tel" id="PhoneNumber" name="PhoneNumber" class="form-input" required 
                        value="<?php echo isset($_POST['PhoneNumber']) ? htmlspecialchars($_POST['PhoneNumber']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="UserName" class="form-label">Username</label>
                <input type="text" id="UserName" name="UserName" class="form-input" 
                        value="<?php echo isset($_POST['UserName']) ? htmlspecialchars($_POST['UserName']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="Password" class="form-label">Password <span class="required">*</span></label>
                <div class="password-container">
                    <input type="password" id="Password" name="Password" class="form-input" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('Password')">👁️</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ConfirmPassword" class="form-label">Confirm Password <span class="required">*</span></label>
                <div class="password-container">
                    <input type="password" id="ConfirmPassword" name="ConfirmPassword" class="form-input" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('ConfirmPassword')">👁️</button>
                </div>
            </div>
            
            <button type="submit" name="submit" class="submit-btn">Submit</button>
        </form>
        
        <div class="divider">Or</div>
        
        <div class="social-login">
            <button class="social-btn facebook-btn" onclick="facebookLogin()">f</button>
            <button class="social-btn google-btn" onclick="googleLogin()">G</button>
        </div>
        
        <div class="divider">Or</div>
        
        <div class="login-link">
            <p>Already have an account?</p>
            <a href="login.php" class="login-btn">Log in</a>
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
            // Facebook login integration
            alert('Facebook login integration would go here');
        }
        
        function googleLogin() {
            // Google login integration
            alert('Google login integration would go here');
        }
        
        // Form validation (client-side)
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('Password').value;
            const confirmPassword = document.getElementById('ConfirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
        // --- MODIFIED PHONE NUMBER FORMATTING ---
        document.getElementById('PhoneNumber').addEventListener('input', function(e) {
            // Remove all non-digit characters from the input value
            let value = e.target.value.replace(/\D/g, ''); 
            e.target.value = value;
        });
    </script>
</body>
</html>