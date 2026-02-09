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

if (!$user) {
    die("User not found in database.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | SRDI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark: #fbfcfc;
            --card-dark: #1e293b;
            --accent-blue: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .profile-wrapper {
            width: 100%;
            max-width: 550px;
            background: var(--card-dark);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        /* Subtle glow effect top right */
        .profile-wrapper::before {
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

        .profile-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .profile-header .avatar-icon {
            font-size: 3.5rem;
            color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.1);
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 15px;
            border: 2px solid rgba(59, 130, 246, 0.2);
        }

        .profile-header h2 {
            font-size: 1.75rem;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .badge-role {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            gap: 16px;
        }

        .info-box {
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.3);
            transition: all 0.3s ease;
        }

        .info-box:hover {
            border-color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.05);
        }

        .info-box label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box label i {
            color: var(--accent-blue);
        }

        .info-box p {
            font-size: 1rem;
            margin: 0;
            font-weight: 500;
            color: var(--text-main);
        }

        .actions {
            margin-top: 30px;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background-color: var(--accent-blue);
            border: none;
            color: white;
            flex: 2;
        }

        .btn-edit:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
        }

        .btn-back {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            flex: 1;
        }

        .btn-back:hover {
            border-color: var(--text-muted);
            color: var(--text-main);
            background: rgba(255,255,255,0.05);
        }
.profile-header .avatar-icon {
    background: transparent;
    border: none;
    width: auto;
    height: auto;
    font-size: 5rem;
}


    </style>
</head>

<body>

    <div class="profile-wrapper">
        <div class="profile-header">
            <div class="avatar-icon">
            <i class="bi bi-person-circle"></i>
            </div>
            <h2 class="fw-bold">
                <?= strtoupper(htmlspecialchars($user['firstname'] . " " . $user['lastname'])); ?>
            </h2>
            <div class="mt-2">
                <span class="badge-role">
                    <?= htmlspecialchars($user['typename']); ?>
                </span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <label><i class="bi bi-person"></i> First Name</label>
                <p><?= htmlspecialchars($user['firstname']); ?></p>
            </div>

            <div class="info-box">
                <label><i class="bi bi-person-lines-fill"></i> Middle Name</label>
                <p><?= !empty($user['middlename']) ? htmlspecialchars($user['middlename']) : '---'; ?></p>
            </div>

            <div class="info-box">
                <label><i class="bi bi-person-fill"></i> Last Name</label>
                <p><?= htmlspecialchars($user['lastname']); ?></p>
            </div>

            <div class="info-box">
                <label><i class="bi bi-envelope-at"></i> Email Address</label>
                <p><?= htmlspecialchars($user['email']); ?></p>
            </div>

            <div class="info-box">
                <label><i class="bi bi-geo-alt"></i> Home Address</label>
                <p><?= htmlspecialchars($user['address']); ?></p>
            </div>
        </div>

        <div class="actions d-flex">
            <a href="dashboard.php" class="btn btn-back">
                <i class="bi bi-arrow-left me-2"></i>Back
            </a>
            <a href="edit_profile.php" class="btn btn-edit">
                <i class="bi bi-pencil-square me-2"></i>Edit Profile
            </a>
        </div>
    </div>

    <?php
    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
        $msg = $_SESSION['message'];
        unset($_SESSION['message']); 
    ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
           Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $msg; ?>',
    confirmButtonColor: '#3b82f6'
});

        </script>
    <?php } ?>

</body>
</html>