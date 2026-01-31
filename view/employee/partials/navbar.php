<?php
//session_start(); I removed  bec it was causing some error and was called twice
require_once "../../controller/Main.php";

$db = new db();

if (isset($_GET['mark_read'])) {

    $user_id = $_SESSION['user_id'] ?? 0;
    $type_id = $_SESSION['type_id'] ?? 0;

    $db->markNotificationsAsRead($user_id, $type_eid);

    echo "OK";
    exit;
}

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;


$notifications = $db->getNotifications($user_id, 10);



// I NEED TO COMMENT THIS PART BEC. THE COUNTING ISN'T ACCURATE
// $unreadCount = 0;
// foreach ($notifications as $notif) {
//     if ($notif['status'] == 0) {
//         $unreadCount++;
//     }
// }

$unreadCount = $db->getUnreadNotificationCount($user_id);

?>

<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="dashboard.php">SRDI Dashboard</a>

    <ul class="navbar-nav ms-auto me-3 me-lg-4">

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle position-relative" id="notificationDropdown"
                href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">

                <i class="fas fa-bell fa-fw"></i>

                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unreadCount ?>
                        <span class="visibly-hidden">unread notifications</span>
                    </span>
                <?php endif; ?>

            </a>

            <!-- Scrollable Dropdown -->
            <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notificationDropdown"
                style="width:350px; max-height:400px; overflow-y:auto; border:1px solid #ddd; border-radius:5px;">

                <li class="dropdown-header fw-bold">Notifications</li>
                <li>
                    <hr class="dropdown-divider">
                </li>

                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <li>
                            <?php 
                            // 👈 Check kung may redirect_url, kung wala use '#'
                            $href = !empty($notif['redirect_url']) ? htmlspecialchars($notif['redirect_url']) : '#';
                            // 👈 Bold if unread
                            $boldClass = $notif['status'] == 0 ? 'fw-bold' : '';
                            ?>
                            <!-- 👈 Changed href from "#" to dynamic $href -->
                            <a class="dropdown-item <?= $boldClass ?> mb-1" href="<?= $href ?>">
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($notif['created_at'])) ?></small><br>
                                <span><?= htmlspecialchars($notif['message']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><a class="dropdown-item text-center text-muted" href="#">No notifications</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <li class="nav-item dropdown ms-3">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user fa-fw"></i> <?= htmlspecialchars($fullname) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="http://localhost/srdi_updated/view/auth/logout.php">Logout</a></li>
            </ul>
        </li>
    </ul>
</nav>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const bell = document.getElementById("notificationDropdown");

        bell.addEventListener("click", function() {

            fetch("?mark_read=1", {
                    method: "GET"
                })
                .then(res => res.text())
                .then(data => {

                    document.querySelectorAll('.badge.bg-danger').forEach(el => el.remove());
                });
        });
    });
</script>