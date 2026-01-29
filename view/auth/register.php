<?php
session_start();
require_once "../../controller/Main.php";

$db = new db();
$message = [];

$firstname = $middlename = $lastname = $email = $password = $confirm_password = $address = "";

if (isset($_POST['submit'])) {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (!$firstname || !$lastname || !$email || !$password || !$confirm_password || !$address) {
        $message[] = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $message[] = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message[] = "Please enter a valid email address.";
    } elseif (!str_ends_with($email, "@dmmmsu.edu.ph")) {
        $message[] = "Only emails ending with @dmmmsu.edu.ph are allowed.";
    } elseif ($db->isEmailExists($email)) {
        $message[] = "Email is already registered.";
    } else {
        $registered = $db->registerUser($firstname, $middlename, $lastname, $email, $password, $address);
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
    <title>Register</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: url('https://cdn.pixabay.com/photo/2022/08/14/01/35/leaves-7384743_1280.jpg') no-repeat center/cover;
        }

        .glass {
            width: 400px;
            padding: 50px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            color: #fff;
        }

        .glass h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        input {
            width: 95%;
            padding: 12px;
            margin: 8px 0;
            border-radius: 10px;
            border: none;
            background: white;
            color: #fff;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.3);
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .login-link {
            margin-top: 15px;
            text-align: center;
        }

        .login-link a {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="glass">
        <h2>Create Account</h2>

        <?php if (!empty($message)) : ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Oops!',
                    html: `<?php echo implode('<br>', $message); ?>`
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="firstname" placeholder="First Name" value="<?= htmlspecialchars($firstname) ?>">
            <input type="text" name="middlename" placeholder="Middle Name" value="<?= htmlspecialchars($middlename) ?>">
            <input type="text" name="lastname" placeholder="Last Name" value="<?= htmlspecialchars($lastname) ?>">
            <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($email) ?>">
            <input type="password" name="password" placeholder="Password">
            <input type="password" name="confirm_password" placeholder="Confirm Password">
            <input type="text" name="address" placeholder="Address" value="<?= htmlspecialchars($address) ?>">
            <button type="submit" name="submit">Register</button>
        </form>

        <!-- Login Link -->
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <?php
    // Show session message (success) if available
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        echo "<script>
        Swal.fire({
            icon: '" . $_SESSION['message_type'] . "',
            title: 'Success!',
            text: '" . $_SESSION['message'] . "'
        });
    </script>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>

</body>

</html>