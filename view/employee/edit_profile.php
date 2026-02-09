<?php

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_start();
require_once "../../controller/Main.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$db = new db();
$userId = $_SESSION['user_id'];
$user = $db->getEmployeeById($userId);

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstname  = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname   = trim($_POST['lastname']);
    $email      = trim($_POST['email']);
    $address    = trim($_POST['address']);

    // Email validation: must end with @dmmmsu.edu.ph
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@dmmmsu\.edu\.ph$/', $email)) {
        $error = "Email must end with @dmmmsu.edu.ph.";
    } else {
        $updated = $db->updateEmployeeProfile($userId, $firstname, $middlename, $lastname, $email, $address);

        if ($updated) {
            // Update session values immediately
            $_SESSION['fullname'] = trim($firstname . ' ' . $lastname);

            // Get updated type name from DB (optional, in case type changes)
            $userUpdated = $db->getEmployeeById($userId);
            $_SESSION['typeName'] = $userUpdated['typename'] ?? $_SESSION['typeName'];

            // Set success message for SweetAlert in profile.php
            $_SESSION['message'] = "Profile updated successfully.";

            // Redirect to profile page
            header("Location: profile.php");
            exit;
        } else {
            $error = "Failed to update profile.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
:root {
    --bg-dark: #0f172a;
    --card-dark: #1e293b;
    --accent-blue: #3b82f6;
    --text-main: #f8fafc;
    --text-muted: #94a3b8;
    --border-color: #334155;
}

body {
    background-color: var(--bg-dark);
    min-height: 100vh;
    padding: 30px;
    font-family: "Inter", sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-main);
}

/* MAIN CARD */
.form-wrapper {
    width: 100%;
    max-width: 650px;
    padding: 40px;
    background: var(--card-dark);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    box-shadow: 0 20px 50px rgba(0,0,0,0.35);
    position: relative;
    overflow: hidden;
}

/* subtle blue glow */
.form-wrapper::before {
    content: "";
    position: absolute;
    top: -50px;
    right: -50px;
    width: 150px;
    height: 150px;
    background: var(--accent-blue);
    filter: blur(80px);
    opacity: 0.2;
}

/* TITLE */
.page-title {
    text-align: center;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 35px;
    letter-spacing: -0.5px;
}

.page-title::after {
    content: "Update your personal information";
    display: block;
    font-size: 0.85rem;
    font-weight: 400;
    color: var(--text-muted);
    margin-top: 6px;
}

/* LABELS */
.form-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
}

/* INPUTS */
.form-control,
textarea {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 12px 14px;
    color: var(--text-main);
    transition: all 0.3s ease;
}

.form-control::placeholder {
    color: #64748b;
}

.form-control:focus,
textarea:focus {
    background: rgba(15, 23, 42, 0.8);
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    color: var(--text-main);
}

/* BUTTONS */
.btn-custom {
    padding: 12px 22px;
    border-radius: 12px;
    font-weight: 600;
}

.btn-primary {
    background-color: var(--accent-blue);
    border: none;
}

.btn-primary:hover {
    background-color: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59,130,246,0.25);
}

.btn-outline-secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-muted);
}

.btn-outline-secondary:hover {
    border-color: var(--text-muted);
    color: var(--text-main);
    background: rgba(255,255,255,0.05);
}

/* ALERT */
.alert-danger {
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.3);
    color: #fecaca;
}

    </style>
</head>

<body>

    <div class="form-wrapper">

        <h2 class="page-title">Edit Profile</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($user['firstname']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control" value="<?= htmlspecialchars($user['middlename']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($user['lastname']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?= htmlspecialchars($user['email']); ?>"
                    required
                    pattern="^[a-zA-Z0-9._%+-]+@dmmmsu\.edu\.ph$"
                    title="Email must end with @dmmmsu.edu.ph">

            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address']); ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="profile.php" class="btn btn-outline-secondary btn-custom">Cancel</a>
                <button type="submit" class="btn btn-primary btn-custom">Save Changes</button>
            </div>

        </form>

    </div>

</body>

</html>