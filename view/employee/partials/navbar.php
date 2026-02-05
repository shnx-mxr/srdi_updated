<?php
require_once "../../controller/Main.php";

$db = new db();

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$notifications = $db->getNotifications($user_id, 10);
$unreadCount = $db->getUnreadNotificationCount($user_id);
?>

<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="dashboard.php"><strong>SRDI RDTS</strong></a>

    <ul class="navbar-nav ms-auto me-3 me-lg-4">

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle position-relative" id="notificationDropdown"
                href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">

                <i class="fas fa-bell fa-fw"></i>

                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notif-badge">
                        <?= $unreadCount ?>
                        <span class="visually-hidden">unread notifications</span>
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
                            // Build redirect URL with notification ID
                            if (!empty($notif['redirect_url'])) {
                                $href = "mark_notification_read.php?id=" . $notif['id'] . "&redirect=" . urlencode($notif['redirect_url']);
                            } else {
                                $href = "#";
                            }
                            
                            // Bold if unread
                            $boldClass = $notif['status'] == 0 ? 'fw-bold' : '';
                            $bgClass = $notif['status'] == 0 ? 'bg-light' : '';
                            ?>
                            <a class="dropdown-item <?= $boldClass ?> <?= $bgClass ?> mb-1" href="<?= $href ?>">
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
                <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
            </ul>
        </li>
    </ul>
</nav>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Handle notification click
    document.querySelectorAll('.dropdown-item[href*="mark_notification_read"]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.href;
            const urlParams = new URLSearchParams(url.split('?')[1]);
            const notifId = urlParams.get('id');
            const redirectUrl = urlParams.get('redirect');
            
            // Mark as read via AJAX
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'mark_read=1&notification_id=' + notifId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update badge count
                    const badge = document.getElementById('notif-badge');
                    if (badge) {
                        if (data.unreadCount > 0) {
                            badge.textContent = data.unreadCount;
                        } else {
                            badge.remove();
                        }
                    }
                    // Redirect to target page
                    window.location.href = redirectUrl;
                }
            });
        });
    });
});
</script>