<?php
session_start();
require_once "../../controller/Main.php";

// Handle AJAX request to mark as read and return new count
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $user_id = $_SESSION['user_id'] ?? 0;
    
    $db = new db();
    $db->markNotificationAsRead($notification_id);
    
    // Get new unread count
    $newCount = $db->getUnreadNotificationCount($user_id);
    
    echo json_encode(['success' => true, 'unreadCount' => $newCount]);
    exit;
}

// Handle redirect request
if (isset($_GET['id']) && isset($_GET['redirect'])) {
    $notification_id = intval($_GET['id']);
    $redirect_url = $_GET['redirect'];
    
    $db = new db();
    $db->markNotificationAsRead($notification_id);
    
    // Redirect to the target page
    header("Location: " . $redirect_url);
    exit;
}
?>