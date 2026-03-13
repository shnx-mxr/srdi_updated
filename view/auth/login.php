<?php
session_start();
require_once "../../controller/Main.php";

$db = new db();
$message = [];
$email = $password = "";

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $message[] = "Please enter both email and password.";
    } else {
        $user = $db->checkUsers($email, $password);

        if (is_array($user) && !isset($user['error'])) {
            // Login success: set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = trim($user['firstname'] . ' ' . $user['lastname']);
            $_SESSION['user_role'] = $user['role_name'] ?? 'Unknown';
            $_SESSION['type_id'] = $user['type_id'] ?? 0;
           $_SESSION['branch'] = $user['branch'] ?? 'Not Assigned';
            $_SESSION['message'] = "Welcome, {$_SESSION['fullname']} ({$_SESSION['user_role']})!";
            $_SESSION['message_type'] = 'success';

            header("Location: ../../view/employee/dashboard.php");
            exit;
        } else {
            $message[] = $user['message'] ?? 'Login failed.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SRDI RDTS</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

     body {
    min-height: 100vh;
    display: flex;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: white;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto; ✅
}


        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(34,197,94,0.05)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            opacity: 0.4;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1400px;
            margin: auto;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            min-height: 600px;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(34, 197, 94, 0.15);
        }

        .left-panel::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(34, 197, 94, 0.15);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(34, 197, 94, 0.1);
            border-radius: 50%;
            bottom: -80px;
            left: -80px;
        }

        .branding {
            text-align: center;
            z-index: 2;
        }

        .logo-container {
            margin-bottom: 30px;
        }

        .srdi-logo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid rgba(34, 197, 94, 0.3);
            padding: 10px;
            background: white;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.2);
        }

        .branding h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .branding p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 400px;
            margin: 0 auto;
        }

        .features {
            margin-top: 50px;
            z-index: 2;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            opacity: 0.95;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(34, 197, 94, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .right-panel {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #64748b;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #22c55e;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }

        input::placeholder {
            color: #94a3b8;
        }

        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(34, 197, 94, 0.4);
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .register-link {
            margin-top: 30px;
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .register-link p {
            color: #64748b;
            font-size: 14px;
        }

        .register-link a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .register-link a:hover {
            color: #16a34a;
            text-decoration: underline;
        }

        .password-toggle {
            position: relative;
        }

        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
                margin: 20px;
            }

            .left-panel {
                padding: 40px;
                min-height: auto;
            }

            .features {
                display: none;
            }

            .right-panel {
                padding: 40px;
            }

            .branding h1 {
                font-size: 24px;
            }

            .srdi-logo {
                width: 100px;
                height: 100px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1e1e1e;
        }

        ::-webkit-scrollbar-thumb {
            background: #22c55e;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #16a34a;
        }
        .password-wrapper {
    position: relative;
}

.password-wrapper input {
    width: 100%;
    padding: 14px 16px;
    height: 44px;           
    box-sizing: border-box;
   border-radius: 12px;
   border: 2px solid #e2e8f0;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f8fafc;
    color: #1e293b;
    font-family: 'Inter', sans-serif;
}


         
           
.password-wrapper input[type="password"]:focus{
          border-color: #22c55e;
        }

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #666;
    font-size: 16px;
}

.toggle-password:hover {
    color: #000;
}

    </style>
</head>

<body>

    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="left-panel">
            <div class="branding">
                <div class="logo-container">
                    <img src="https://www.dmmmsu.edu.ph/wp-content/uploads/2020/11/srdi_LOGO-20200130-1024x1024.png" class="srdi-logo" alt="DMMMSU SRDI Logo">
                </div>
                <h1>DMMMSU SRDI</h1>
                <p>Research Documents Tracking System</p>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <div>Track research progress</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">🔬</div>
                    <div>Manage research projects efficiently</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">👥</div>
                    <div>Collaborate with research teams</div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="right-panel">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Please sign in to access your research dashboard</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           placeholder="@dmmmsu.edu.ph" 
                           value="<?= htmlspecialchars($email) ?>"
                           required>
                </div>

                <div class="form-group">
    <label for="password">Password</label>
    <div class="password-wrapper">
        <input type="password"
               id="password"
               name="password"
               placeholder="Enter your password"
               required>
<i class="fa-solid fa-eye-slash toggle-password"
   onclick="toggleLoginPassword(this)"></i>


    </div>
</div>


                <button type="submit" name="submit">Sign In</button>
            </form>

            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Create an account</a></p>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Authentication Failed',
                html: `<?= implode('<br>', $message); ?>`,
                confirmButtonColor: '#22c55e',
                customClass: {
                    popup: 'rounded-popup'
                }
            });
        </script>
    <?php endif; ?>
<script>
function toggleLoginPassword(icon) {
    const input = icon.previousElementSibling;

    if (input.type === "password") {
        // currently hidden → SHOW password
        input.type = "text";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    } else {
        // currently shown → HIDE password
        input.type = "password";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    }
}
</script>



</body>

</html>