<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userData = [
        'username' => filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'full_name' => filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
        'role' => filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING)
    ];

    // Validate inputs
    if (!$userData['username'] || !$userData['password'] || !$userData['full_name'] || !$userData['email'] || !$userData['role']) {
        $error = 'All fields are required';
    } elseif ($userData['password'] !== $userData['confirm_password']) {
        $error = 'Passwords do not match';
    } elseif (strlen($userData['password']) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$userData['username']]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username already exists';
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$userData['email']]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Email address already exists';
                } else {
                    if (registerUser($userData)) {
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card signup-card">
            <div class="auth-header">
                <h1><i class="bi bi-person-plus"></i> Create Account</h1>
                <p>Join the Digital Logbook System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert error">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <i class="bi bi-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" id="signupForm">
                <div class="form-group">
                    <label for="full_name">
                        <i class="bi bi-person"></i>
                        Full Name
                    </label>
                    <input type="text" id="full_name" name="full_name" required autofocus>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="bi bi-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="username">
                        <i class="bi bi-person-badge"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="role">
                        <i class="bi bi-shield-lock"></i>
                        Role
                    </label>
                    <select name="role" id="role" required>
                        <option value="">Select Role</option>
                        <option value="shift_engineer">Shift Engineer</option>
                        <option value="plant_supervisor">Plant Supervisor</option>
                        <option value="compliance_team">Compliance Team</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="bi bi-lock"></i>
                        Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="bi bi-lock-fill"></i>
                        Confirm Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar"></div>
                    <span class="strength-text">Password Strength</span>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="bi bi-person-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-links">
                Already have an account? 
                <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // Password strength checker
        const password = document.getElementById('password');
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');

        password.addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            updateStrengthIndicator(strength);
        });

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            return strength;
        }

        function updateStrengthIndicator(strength) {
            const width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';

            if (strength === 0) {
                strengthBar.style.backgroundColor = '#ef4444';
                strengthText.textContent = 'Very Weak';
            } else if (strength <= 2) {
                strengthBar.style.backgroundColor = '#eab308';
                strengthText.textContent = 'Weak';
            } else if (strength <= 3) {
                strengthBar.style.backgroundColor = '#22c55e';
                strengthText.textContent = 'Medium';
            } else if (strength <= 4) {
                strengthBar.style.backgroundColor = '#3b82f6';
                strengthText.textContent = 'Strong';
            } else {
                strengthBar.style.backgroundColor = '#6366f1';
                strengthText.textContent = 'Very Strong';
            }
        }

        // Form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
            }
        });
    </script>
</body>
</html> 