<?php
session_start();
require_once "../../controller/Main.php";

$db = new db();
$employee_type = "";


$message = [];

$branch = ($employee_type == 2 && isset($_POST['branch'])) ? $_POST['branch'] : null;

$firstname = $middlename = $lastname = $email = $password = $confirm_password = $address = $employee_type = $branch = "";
$message = [];


if (isset($_POST['submit'])) {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $employee_type = isset($_POST['employee_type']) ? intval($_POST['employee_type']) : 0;

    // ✅ Set branch ONLY after employee_type is known
    $branch = ($employee_type == 2 && isset($_POST['branch'])) ? trim($_POST['branch']) : null;

    // Validation
    if (!$firstname || !$lastname || !$email || !$password || !$confirm_password || !$address || !$employee_type) {
        $message[] = "Please fill in all required fields.";
    } elseif (!$employee_type || !in_array($employee_type, [1,2,3,4,5,6])) {
        $message[] = "Please select a valid Employee Type.";
    } elseif ($password !== $confirm_password) {
        $message[] = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message[] = "Please enter a valid email address.";
    } elseif (!str_ends_with($email, "@dmmmsu.edu.ph")) {
        $message[] = "Only emails ending with @dmmmsu.edu.ph are allowed.";
    } elseif ($db->isEmailExists($email)) {
        $message[] = "Email is already registered.";
    } else {
        // ✅ Pass branch to registerUser
        $registered = $db->registerUser(
            $firstname,
            $middlename,
            $lastname,
            $email,
            $password,
            $address,
            $employee_type,
            $branch // this is now set correctly
        );

        if ($registered) {
            $_SESSION['message'] = 'Registration successful! You may now log in.';
            $_SESSION['message_type'] = 'success';
            header("Location: login.php");
            exit;
        } else {
            $message[] = "Registration failed. Please try again.";
        }
    }
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DMMMSU SRDI Research Tracking</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            background:white;
            position: relative;
  
        }

        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(34,197,94,0.05)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            opacity: 0.4;
        }

        .register-container {
            display: flex;
            width: 100%;
            max-width: 1400px;
            margin: auto;
            background: #ffffff;
   
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            min-height: 650px;
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

        .right-panel {
            flex: 1;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            background: #ffffff;
            overflow-y: auto;
            max-height: 100vh;
        }

        .register-header {
            margin-bottom: 24px;
        }

        .register-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .register-header p {
            color: #64748b;
            font-size: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            letter-spacing: 0.2px;
        }
/* Style for select dropdowns */
select {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8fafc;
    color: #1e293b;
    font-family: 'Inter', sans-serif;
    appearance: none; /* Removes default arrow on some browsers */
    cursor: pointer;
}

/* Focus state for select */
select:focus {
    outline: none;
    border-color: #22c55e;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
}

/* Placeholder color for select (optional) */
select option[value=""] {
    color: #94a3b8;
}

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
        }

        input[type="text"]:focus,
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
            padding: 14px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 4px;
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

        .login-link {
            margin-top: 20px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .login-link p {
            color: #64748b;
            font-size: 14px;
        }

        .login-link a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .login-link a:hover {
            color: #16a34a;
            text-decoration: underline;
        }

        .required {
            color: #ef4444;
        }

        @media (max-width: 968px) {
            .register-container {
                flex-direction: column;
                max-width: 500px;
                margin: 20px;
            }

            .left-panel {
                padding: 40px;
                min-height: auto;
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

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #22c55e;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #16a34a;
        }
    </style>
</head>

<body>

    <div class="register-container">
        <!-- Left Panel - Branding -->
        <div class="left-panel">
            <div class="branding">
                <div class="logo-container">
                    <img src="https://www.dmmmsu.edu.ph/wp-content/uploads/2019/06/SRDI-Logo.jpg" class="srdi-logo" alt="DMMMSU SRDI Logo">
                </div>
                <h1>DMMMSU SRDI</h1>
                <p>Research Tracking System</p>
            </div>
        </div>

        <!-- Right Panel - Registration Form -->
        <div class="right-panel">
            <div class="register-header">
                <h2>Create Your Account</h2>
                <p>Join DMMMSU SRDI research platform</p>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname">First Name <span class="required">*</span></label>
                        <input type="text" 
                               id="firstname" 
                               name="firstname" 
                               placeholder="Enter first name" 
                               value="<?= htmlspecialchars($firstname) ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="middlename">Middle Name</label>
                        <input type="text" 
                               id="middlename" 
                               name="middlename" 
                               placeholder="Enter middle name" 
                               value="<?= htmlspecialchars($middlename) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name <span class="required">*</span></label>
                    <input type="text" 
                           id="lastname" 
                           name="lastname" 
                           placeholder="Enter last name" 
                           value="<?= htmlspecialchars($lastname) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           placeholder="yourname@dmmmsu.edu.ph" 
                           value="<?= htmlspecialchars($email) ?>"
                           required>
                </div>
<div class="form-group">
    <label for="employee_type">Employee Type <span class="required">*</span></label>
    <select id="employee_type" name="employee_type" required>
        <option value="">Select Employee Type </option>
        <option value="1" <?= (isset($employee_type) && $employee_type==1) ? 'selected' : '' ?>>Researcher</option>
        <option value="2" <?= (isset($employee_type) && $employee_type==2) ? 'selected' : '' ?>>Section Head</option>
        <option value="3" <?= (isset($employee_type) && $employee_type==3) ? 'selected' : '' ?>>Division Chief</option>
        <option value="5" <?= (isset($employee_type) && $employee_type==5) ? 'selected' : '' ?>>Records</option>

    </select>
</div>
<div class="form-group" id="branch-container" style="display:none;">
    <label for="branch">Select Branch</label>
    <select id="branch" name="branch">
        <option value="">Select Branch</option>
        <option value="Mulberry">Mulberry</option>
        <option value="Post-Cocoon">Post-Cocoon</option>
        <option value="Silkworm">Silkworm</option>
    </select>
</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Create password"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="Confirm password"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address <span class="required">*</span></label>
                    <input type="text" 
                           id="address" 
                           name="address" 
                           placeholder="Enter your address" 
                           value="<?= htmlspecialchars($address) ?>"
                           required>
                </div>

                <button type="submit" name="submit">Create Account</button>
            </form>

            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Registration Error',
                html: `<?= implode('<br>', $message); ?>`,
                confirmButtonColor: '#22c55e',
                customClass: {
                    popup: 'rounded-popup'
                }
            });
        </script>
    <?php endif; ?>

    <?php
    // Show session message (success) if available
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        echo "<script>
        Swal.fire({
            icon: '" . $_SESSION['message_type'] . "',
            title: 'Success!',
            text: '" . $_SESSION['message'] . "',
            confirmButtonColor: '#22c55e'
        });
    </script>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
<script>const employeeTypeSelect = document.getElementById('employee_type');
const branchContainer = document.getElementById('branch-container');

employeeTypeSelect.addEventListener('change', function() {
    const typeId = parseInt(this.value);

    if(typeId === 2){ // Section Head
        branchContainer.style.display = 'block';
    } else {
        branchContainer.style.display = 'none';
    }
});
</script>
</body>

</html>