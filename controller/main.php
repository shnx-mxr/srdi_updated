<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class db
{
    private $con;

    public function __construct()
    {
$this->con = mysqli_connect('localhost', 'root', '', 'srdi', 3306);
        if (!$this->con) {
            die("Database connection failed: " . mysqli_connect_error());
        }
    }

    public function getConnection()
    {
        return $this->con;
    }

    // Check if email already exists
    public function isEmailExists($email)
    {
        $email = $this->con->real_escape_string($email);
        $query = "SELECT email FROM employee WHERE email = '$email' LIMIT 1";
        $result = $this->con->query($query);

        if (!$result) {
            die("Query Failed: " . $this->con->error);
        }

        return $result->num_rows > 0;
    }
    public function getUsersByTypes(array $type_ids)
    {
        // Convert array to comma-separated placeholders
        $placeholders = implode(',', array_fill(0, count($type_ids), '?'));
        $types = str_repeat('i', count($type_ids));

        $sql = "SELECT id FROM employee WHERE type_id IN ($placeholders)";
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }

        // Bind params dynamically
        $stmt->bind_param($types, ...$type_ids);
        $stmt->execute();

        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        $stmt->close();
        return $users;
    }


    // Register a new user (NO ROLE)
public function registerUser($firstname, $middlename, $lastname, $email, $password, $address, $type_id, $branch = null)
{
    $firstname  = $this->con->real_escape_string($firstname);
    $middlename = $this->con->real_escape_string($middlename);
    $lastname   = $this->con->real_escape_string($lastname);
    $email      = $this->con->real_escape_string($email);
    $address    = $this->con->real_escape_string($address);
   $branch     = $this->con->real_escape_string($branch);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $status_id = 1; // still default

 $stmt = $this->con->prepare("
    INSERT INTO employee
    (firstname, middlename, lastname, email, password, address, type_id, status_id, branch)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssiss",
    $firstname,
    $middlename,
    $lastname,
    $email,
    $hashedPassword,
    $address,
    $type_id,
    $status_id,
    $branch
);

    $success = $stmt->execute();

    if ($success) {
        $userId = $this->con->insert_id;
        $this->insertLog($userId, 'User registered');

        // Notifications for Admins/Section Heads
        $result = $this->con->query("SELECT id FROM employee WHERE type_id IN (2, 4)");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $stmt2 = $this->con->prepare("
                    INSERT INTO notifications (user_id, message, research_id, notification_type, redirect_url, status, created_at) 
                    VALUES (?, ?, NULL, 'employee_pending', 'employeepending.php', 0, NOW())
                ");
                $message = "New employee pending approval: $firstname $lastname ($email)";
                $stmt2->bind_param("is", $admin['id'], $message);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }

    return $success;
}


    // Insert a notification
   public function insertNotification($user_id, $message, $research_id = null, $notification_type = null)
{
    $user_id = (int)$user_id;
    $message = $this->con->real_escape_string($message);
    $research_id = $research_id ? (int)$research_id : null;
    $notification_type = $notification_type ? $this->con->real_escape_string($notification_type) : null;
    
    // Auto-generate redirect URL based on notification type
    $redirect_url = null;
    if ($research_id && $notification_type) {
        switch ($notification_type) {
            case 'approved':
                $redirect_url = 'approved.php';
                break;
            case 'revised':
                $redirect_url = 'revised.php';
                break;
            case 'published':
                $redirect_url = 'publish.php';
                break;
            case 'pending':
                $redirect_url = 'pending.php';
                break;
            case 'cancelled':
                $redirect_url = 'cancel.php';
                break;
            default:
                $redirect_url = 'dashboard.php';
        }
    }

    $stmt = $this->con->prepare("
        INSERT INTO notifications (user_id, message, research_id, notification_type, redirect_url, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("isiss", $user_id, $message, $research_id, $notification_type, $redirect_url);

    return $stmt->execute();
}
    public function getResearchById($research_id)
    {
        $stmt = $this->con->prepare("
        SELECT *
        FROM research
        WHERE id = ?
        LIMIT 1
    ");

        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }

        $stmt->bind_param("i", $research_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $research = $result->fetch_assoc();

        $stmt->close();

        return $research;
    }


    //for unread notifications 
    public function getUnreadNotificationCount($user_id)
    {
        $sql = "SELECT COUNT(*) AS total 
            FROM notifications 
            WHERE user_id = ? AND status = 0";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        return (int)$row['total'];
    }

    // Login check
    public function checkUsers($email, $password)
    {
        $email = $this->con->real_escape_string($email);

        $query = "SELECT * FROM employee WHERE email = '$email' LIMIT 1";
        $result = $this->con->query($query);

        if (!$result) {
            die("Query Failed: " . $this->con->error);
        }

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                $status = (int)$user['status_id'];
                $typeId = (int)$user['type_id'];

                // Map type_id to role name
                $roleName = match ($typeId) {
                    1 => 'Researcher',
                    2 => 'Section Head',
                    3 => 'Division Chief',
                    4 => 'Admin',
                    default => 'Unknown'
                };

                // Add role name to the user array
                $user['role_name'] = $roleName;

                // Return user array if status is approved
                if ($status === 2) {
                    $this->insertLog($user['id'], 'User logged in');
                    return $user;
                } elseif ($status === 1) {
                    $this->insertLog($user['id'], 'Pending login attempt');
                    return ['error' => 'pending', 'message' => 'Your account is pending approval.'];
                } else {
                    $this->insertLog($user['id'], 'Rejected login attempt');
                    return ['error' => 'rejected', 'message' => 'Your account is rejected or inactive.'];
                }
            } else {
                return ['error' => 'invalid', 'message' => 'Incorrect password.'];
            }
        } else {
            return ['error' => 'notfound', 'message' => 'User not found.'];
        }
    }


    // Insert activity log
    public function insertLog($user_id, $activity)
    {
        $user_id = (int) $user_id;
        $activity = $this->con->real_escape_string($activity);

        $stmt = $this->con->prepare("INSERT INTO activity_logs (activities, user_id) VALUES (?, ?)");
        $stmt->bind_param("si", $activity, $user_id);

        return $stmt->execute();
    }

    public function getResearchStatusCounts($user_id = null)
    {
        if ($user_id !== null) {
            $query = "SELECT status_id, COUNT(*) AS total FROM research WHERE user_id = ? GROUP BY status_id";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param("i", $user_id);
        } else {
            $query = "SELECT status_id, COUNT(*) AS total FROM research GROUP BY status_id";
            $stmt = $this->con->prepare($query);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $statusMap = [1 => 'pending', 2 => 'approved', 3 => 'revised', 4 => 'cancelled', 5 => 'published'];
        $counts = array_fill_keys(array_values($statusMap), 0);

        while ($row = $result->fetch_assoc()) {
            $id = (int)$row['status_id'];
            if (isset($statusMap[$id])) $counts[$statusMap[$id]] = (int)$row['total'];
        }

        $stmt->close();
        return $counts;
    }

    public function getMonthlyResearchCounts($year, $user_id = null)
    {
        if ($user_id !== null) {
            $query = "SELECT MONTH(created_at) AS month, COUNT(*) AS total FROM research WHERE YEAR(created_at)=? AND user_id=? GROUP BY MONTH(created_at)";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param("ii", $year, $user_id);
        } else {
            $query = "SELECT MONTH(created_at) AS month, COUNT(*) AS total FROM research WHERE YEAR(created_at)=? GROUP BY MONTH(created_at)";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param("i", $year);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['month']] = (int)$row['total'];
        }

        $stmt->close();
        return $data;
    }

    public function getAllResearch($user_id = null)
    {
        $query = "SELECT r.*, e.firstname AS leader_firstname, e.lastname AS leader_lastname
              FROM research r
              LEFT JOIN employee e ON r.user_id = e.id";

        if ($user_id !== null) $query .= " WHERE r.user_id=?";
        $query .= " ORDER BY r.created_at DESC";

        $stmt = $this->con->prepare($query);
        if ($user_id !== null) $stmt->bind_param("i", $user_id);

        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;

        $stmt->close();
        return $data;
    }


    // if  something happened, just remove the comment
    // public function getNotifications($user_id, $limit = 10)
    // {
    //     $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    //     $stmt = $this->con->prepare($sql);
    //     $stmt->bind_param("ii", $user_id, $limit);
    //     $stmt->execute();
    //     $result = $stmt->get_result();

    //     $notifications = [];
    //     while ($row = $result->fetch_assoc()) {
    //         $notifications[] = $row;
    //     }

    //     $stmt->close();
    //     return $notifications;
    // }

    // public function getNotifications($user_id, $limit = 10)
    // {
    //     $sql = "SELECT * 
    //         FROM notifications 
    //         WHERE user_id = ?
    //         ORDER BY created_at DESC
    //         LIMIT ?";

    //     $stmt = $this->con->prepare($sql);
    //     $stmt->bind_param("ii", $user_id, $limit);
    //     $stmt->execute();

    //     return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // }

    public function getNotifications($user_id, $limit = 10)
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    public function markNotificationsAsRead($user_id, $type_id)
    {
        if ($type_id == 1) {
            // Update only unread notifications for this user
            $sql = "UPDATE notifications SET status = 1 WHERE user_id = ? AND status = 0";
            $stmt = $this->con->prepare($sql);
            $stmt->execute([$user_id]);
        } else {
            // Update only notifications for this user, even if type_id != 1
            $sql = "UPDATE notifications SET status = 1 WHERE user_id = ? AND status = 0";
            $stmt = $this->con->prepare($sql);
            $stmt->execute([$user_id]);
        }
    }



    public function getEmployees()
    {
        $sql = "SELECT firstname, lastname FROM employee ORDER BY firstname ASC, lastname ASC";
        $result = $this->con->query($sql);
        $employees = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        return $employees;
    }

  public function uploadResearch($title, $description, $members, $file_name, $user_id, $user_type, $startDate, $endDate)
{
    $uploaderName = $_SESSION['fullname'] ?? 'You';

    $stmt = $this->con->prepare(
        "INSERT INTO research 
        (title, description, member, filePath, startDate, endDate, status_id, type_id, user_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())"
    );
    $stmt->bind_param("ssssssis", $title, $description, $members, $file_name, $startDate, $endDate, $user_type, $user_id);
    $success = $stmt->execute();

    if ($success) {
        $research_id = $this->con->insert_id; // 🎯 Get the inserted research ID

        // Notification for uploader
        $this->insertNotification($user_id, "New research uploaded by You: {$title}", $research_id, "pending");

        // Notification for intended roles (type_id 2 or 4), excluding uploader
        $result = $this->con->query("SELECT id FROM employee WHERE type_id IN (2, 4) AND id != {$user_id}");
        if ($result) {
            while ($user = $result->fetch_assoc()) {
                $messageToSend = "New research uploaded by {$uploaderName}: {$title}";
                $this->insertNotification($user['id'], $messageToSend, $research_id, "pending");
            }
        }
    }

    $stmt->close();
    return $success;
}
    // Fetch all research by status_id
    public function getResearchByStatus($status_id)
    {
        // Prepare the SQL statement
        $stmt = $this->con->prepare("SELECT * FROM research WHERE status_id = ? ORDER BY created_at DESC");
        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }

        // Bind the status_id parameter
        $stmt->bind_param("i", $status_id);

        // Execute the statement
        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        // Get the result set
        $result = $stmt->get_result();
        $research = $result->fetch_all(MYSQLI_ASSOC);

        // Close the statement
        $stmt->close();

        return $research;
    }

    public function getResearchForUser($user_id, $type_id)
    {
        if ($type_id == 1) {
            // Type 1 sees only their own research
            $stmt = $this->con->prepare("SELECT * FROM research WHERE user_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $user_id);
        } else {
            // Type 2,3,4 see all research
            $stmt = $this->con->prepare("SELECT * FROM research ORDER BY id DESC");
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $research = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $research;
    }

    // Update research status (Approve = 2, Revise = 3)
    public function updateResearchStatus($research_id, $status_id, $updatedByUserId)
    {
        // 1️⃣ Update research status and desisyon_id
        $stmt = $this->con->prepare("
        UPDATE research 
        SET status_id = ?, desisyon_id = ?, updated_at = NOW() 
        WHERE id = ?
    ");
        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }
        $stmt->bind_param("iii", $status_id, $updatedByUserId, $research_id);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) return false; // stop if update failed

        // 2️⃣ Fetch research info
        $stmt2 = $this->con->prepare("SELECT title, user_id FROM research WHERE id = ?");
        $stmt2->bind_param("i", $research_id);
        $stmt2->execute();
        $researchData = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if (!$researchData) return false;

        $researchOwnerId = $researchData['user_id'];
        $researchTitle = $researchData['title'];

        // 3️⃣ Fetch updater name
        $stmt3 = $this->con->prepare("SELECT firstname, lastname FROM employee WHERE id = ?");
        $stmt3->bind_param("i", $updatedByUserId);
        $stmt3->execute();
        $updater = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();

        $updaterName = $updater ? $updater['firstname'] . ' ' . $updater['lastname'] : 'Unknown';

        // 4️⃣ Determine status text
        $statusText = $status_id == 2 ? "Approved" : ($status_id == 3 ? "Revised" : "Unknown");

        // 5️⃣ Insert notification for research owner
        $message = "Your research '{$researchTitle}' has been {$statusText} by {$updaterName})";
        $this->insertNotification($researchOwnerId, $message);

        // 6️⃣ Insert activity log for updater
        $activity = "Updated research '{$researchTitle}' status to {$statusText})";
        $this->insertLog($updatedByUserId, $activity);

        return true;
    }

    public function getResearchByStatuses(array $statuses): array
    {
        if (empty($statuses)) return [];

        // Prepare placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        $types = str_repeat('i', count($statuses)); // 'i' for integer
        $stmt = $this->con->prepare("SELECT * FROM research WHERE status_id IN ($placeholders) ORDER BY startDate DESC");

        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$statuses);
        $stmt->execute();
        $result = $stmt->get_result();
        $research = $result->fetch_all(MYSQLI_ASSOC);

        $stmt->close();
        return $research;
    }
    public function getEmployeeName($id)
    {
        $stmt = $this->con->prepare("SELECT firstname, lastname FROM employee WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ? $res['firstname'] . ' ' . $res['lastname'] : 'Unknown';
    }

    public function updateResearch($research_id, $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
            // Determine type: i=int, s=string
            $types .= is_int($value) ? 'i' : 's';
        }

        $sql = "UPDATE research SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $this->con->error);
        }

        $params[] = $research_id;
        $types .= 'i'; // research_id is int

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    // Get user's type_id
private function getUserTypeId($user_id)
{
    $stmt = $this->con->prepare("SELECT type_id FROM employee WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (int)$result['type_id'] : 0;
}
public function updateResearchStatusExtended($research_id, $status_id, $updatedByUserId, $comment = null, $complianceFile = null)
{
    $research_id = intval($research_id);
    $status_id = intval($status_id);
    $updatedByUserId = intval($updatedByUserId);
    
    // Get user's type_id
    $userTypeId = $this->getUserTypeId($updatedByUserId);

    switch ($status_id) {
        case 2: // Approved
            $statusText = "Approved";
            $notifType = "approved";
            $stmt = $this->con->prepare("
                UPDATE research 
                SET status_id = ?, desisyon_id = ?, decided_by_role = ?, comment = NULL, compliance = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("iiii", $status_id, $updatedByUserId, $userTypeId, $research_id);
            break;

        case 3: // Revised
            $statusText = "Revised";
            $notifType = "revised";
            $stmt = $this->con->prepare("
                UPDATE research 
                SET status_id = ?, desisyon_id = ?, decided_by_role = ?, comment = ?, compliance = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("iiissi", $status_id, $updatedByUserId, $userTypeId, $comment, $complianceFile, $research_id);
            break;

        case 4: // Cancelled
            $statusText = "Cancelled";
            $notifType = "cancelled";
            $stmt = $this->con->prepare("
                UPDATE research 
                SET status_id = ?, desisyon_id = ?, decided_by_role = ?, comment = ?, compliance = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("iiissi", $status_id, $updatedByUserId, $userTypeId, $comment, $complianceFile, $research_id);
            break;

        case 5: // Published
            $statusText = "Published";
            $notifType = "published";
            $stmt = $this->con->prepare("
                UPDATE research 
                SET status_id = ?, desisyon_id = ?, decided_by_role = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("iiii", $status_id, $updatedByUserId, $userTypeId, $research_id);
            break;

        default:
            return false;
    }

    $stmt->execute();
    $stmt->close();

    // Fetch research info
    $stmt2 = $this->con->prepare("SELECT title, user_id FROM research WHERE id = ?");
    $stmt2->bind_param("i", $research_id);
    $stmt2->execute();
    $info = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    $owner = $info['user_id'];
    $title = stripslashes($info['title']);
    $updater = $this->getEmployeeName($updatedByUserId);

    // Notification logic based on status
    if ($status_id === 5) {
        // Published - Notify EVERYONE
        
        // 1. Notify Researcher (owner)
        $message = "Congratulations! Your research '{$title}' has been published by {$updater}. You can now add publication details.";
        $this->insertNotification($owner, $message, $research_id, $notifType);
        
        // 2. Notify Records (type_id = 5)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 5");
        if ($result) {
            while ($records = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been published by {$updater}.";
                $this->insertNotification($records['id'], $message, $research_id, $notifType);
            }
        }
        
        // 3. Notify Section Head (type_id = 2)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
        if ($result) {
            while ($secHead = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been published by {$updater}.";
                $this->insertNotification($secHead['id'], $message, $research_id, $notifType);
            }
        }
        
        // 4. Notify Division Chief (type_id = 3)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 3");
        if ($result) {
            while ($divChief = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been published by {$updater}.";
                $this->insertNotification($divChief['id'], $message, $research_id, $notifType);
            }
        }
        
        // 5. Notify Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been published by {$updater}.";
                $this->insertNotification($admin['id'], $message, $research_id, $notifType);
            }
        }
        
    } elseif ($status_id === 3) {
        // Revision - Notify Researcher, Admin, and others based on who revised
        $reason = $comment ?: 'required revisions';
        $message = "Your research '{$title}' needs revision. Reason: {$reason}. Revised by {$updater}.";
        $this->insertNotification($owner, $message, $research_id, $notifType);
        
        // Notify Admin
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been sent for revision by {$updater}.";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, $notifType);
            }
        }
        
        // If Section Head revised, notify Div Chief
        // if ($userTypeId == 2) {
        //     $result = $this->con->query("SELECT id FROM employee WHERE type_id = 3");
        //     if ($result) {
        //         while ($divChief = $result->fetch_assoc()) {
        //             $dcMessage = "Research '{$title}' has been sent for revision by Section Head ({$updater}).";
        //             $this->insertNotification($divChief['id'], $dcMessage, $research_id, $notifType);
        //         }
        //     }
        // }
        
        // If Div Chief revised, notify Section Head
        if ($userTypeId == 3) {
            $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
            if ($result) {
                while ($secHead = $result->fetch_assoc()) {
                    $shMessage = "Research '{$title}' has been sent for revision by Division Chief ({$updater}).";
                    $this->insertNotification($secHead['id'], $shMessage, $research_id, $notifType);
                }
            }
        }
        
    } elseif ($status_id === 2) {
        // Approved - Notify Researcher, Admin, and next person in chain
        $message = "Your research '{$title}' has been approved by {$updater}.";
        $this->insertNotification($owner, $message, $research_id, $notifType);
        
        // Notify Admin
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been approved by {$updater}.";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, $notifType);
            }
        }
        
        // If Section Head approved, notify Div Chief
        if ($userTypeId == 2) {
            $result = $this->con->query("SELECT id FROM employee WHERE type_id = 3");
            if ($result) {
                while ($divChief = $result->fetch_assoc()) {
                    $dcMessage = "Research '{$title}' has been approved by Section Head ({$updater}) and is ready for your review.";
                    $this->insertNotification($divChief['id'], $dcMessage, $research_id, $notifType);
                }
            }
        }
        
    } elseif ($status_id === 4) {
        // Cancelled - Notify Researcher and Admin
        $reason = $comment ?: 'no reason provided';
        $message = "Your research '{$title}' has been cancelled by {$updater}. Reason: {$reason}";
        $this->insertNotification($owner, $message, $research_id, $notifType);
        
        // Notify Admin
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been cancelled by {$updater}.";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, $notifType);
            }
        }
    } else {
        // Other statuses - Notify owner and admin
        $message = "Your research '{$title}' has been {$statusText} by {$updater}.";
        $this->insertNotification($owner, $message, $research_id, $notifType);
        
        // Notify Admin
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' status changed to {$statusText} by {$updater}.";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, $notifType);
            }
        }
    }

    $this->insertLog($updatedByUserId, "Updated research '{$title}' to {$statusText}");

    return true;
}


    // Update a specific employee's status
    public function updateEmployeeStatusById($id, $status)
    {
        $stmt = $this->con->prepare("UPDATE employee SET status_id = ? WHERE id = ? AND status_id = 1");
        $stmt->bind_param("ii", $status, $id);
        return $stmt->execute();
    }
    // In Main.php inside your db class
    // public function getEmployeesByStatus($statusId = 1)
    // {
    //     $statusId = intval($statusId); // sanitize input
    //     $sql = "SELECT * FROM employee WHERE status_id = $statusId";
    //     $result = $this->con->query($sql);

    //     $employees = [];
    //     if ($result && $result->num_rows > 0) {
    //         while ($row = $result->fetch_assoc()) {
    //             $employees[] = $row;
    //         }
    //     }
    //     return $employees;
    // }


    public function getEmployeesByStatus($statusId = 1, $limit = 10, $offset = 0)
{
    $statusId = intval($statusId); // sanitize input
    $limit = intval($limit);
    $offset = intval($offset);

    // Prepare query with LIMIT and OFFSET
    $sql = "SELECT * FROM employee WHERE status_id = $statusId ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $result = $this->con->query($sql);

    $employees = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    return $employees;
}

// Optional: helper function to count total employees for pagination
public function countEmployeesByStatus($statusId = 1)
{
    $statusId = intval($statusId);
    $sql = "SELECT COUNT(*) AS total FROM employee WHERE status_id = $statusId";
    $result = $this->con->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

    // Update employee status
    // Update employee status with log and notification
    public function updateEmployeeStatus($id, $status_id, $admin_id = 0)
    {
        $id = intval($id);
        $status_id = intval($status_id);
        $admin_id = intval($admin_id); // The user performing the action

        // Get old status for logging
        $oldStatusResult = $this->con->query("SELECT status_id, firstname, lastname FROM employee WHERE id = $id");
        if (!$oldStatusResult || $oldStatusResult->num_rows == 0) {
            return false; // Employee not found
        }

        $row = $oldStatusResult->fetch_assoc();
        $oldStatus = $row['status_id'];
        $employeeName = $row['firstname'] . ' ' . $row['lastname'];

        // Update employee status
        $updateSql = "UPDATE employee SET status_id = $status_id, updated_at = NOW() WHERE id = $id";
        $success = $this->con->query($updateSql);

        if ($success) {
            // Determine status text
            $statusText = $status_id == 2 ? 'Approved' : ($status_id == 3 ? 'Rejected' : 'Updated');

            // Insert activity log
            $activity = "Changed status of $employeeName from $oldStatus to $statusText";
            $this->insertLog($admin_id, $activity);

            // Insert notification for employee
            $notifMessage = "Your account status has been $statusText by admin.";
            $this->insertNotification($id, $notifMessage);
        }

        return $success;
    }
    public function getEmployeeTypeName($type_id)
    {
        // Use the correct ID column name: 'id'
        $stmt = $this->con->prepare("SELECT typename FROM employeetype WHERE id = ?");
        $stmt->bind_param("i", $type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['typename'];
        }
        return null;
    }

    //FOR PROFILE INFO
    public function getEmployeeById($id)
    {
        $stmt = $this->con->prepare("
        SELECT 
            e.firstname,
            e.middlename,
            e.lastname,
            e.email,
            e.address,
            et.typename
        FROM employee e
        LEFT JOIN employeetype et ON e.type_id = et.id
        WHERE e.id = ?
    ");

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return ($result->num_rows > 0) ? $result->fetch_assoc() : null;
    }


    //FOR UPDATING THE PROFILE INFO
    public function updateEmployeeProfile($id, $firstname, $middlename, $lastname, $email, $address)
    {
        $stmt = $this->con->prepare("
        UPDATE employee 
        SET firstname = ?, middlename = ?, lastname = ?, email = ?, address = ?
        WHERE id = ?
    ");

        $stmt->bind_param("sssssi", $firstname, $middlename, $lastname, $email, $address, $id);
        return $stmt->execute();
    }

    public function addPublicationDetails($research_id, $pub_title, $pub_date, $pub_link, $publisher)
    {
        $stmt = $this->con->prepare("UPDATE research SET publication_title = ?, publication_date = ?, publication_link = ?, publisher = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $pub_title, $pub_date, $pub_link, $publisher, $research_id);
        return $stmt->execute();
    }

    public function updatePublicationDetails($research_id, $pub_title, $pub_date, $pub_link, $publisher)
    {
        return $this->addPublicationDetails($research_id, $pub_title, $pub_date, $pub_link, $publisher);
    }


    // Mark research as processed by Records
public function markProcessedByRecords($research_id, $records_user_id)
{
    $research_id = (int)$research_id;
    $records_user_id = (int)$records_user_id;
    
    $stmt = $this->con->prepare("
        UPDATE research 
        SET processed_by_records = 1, 
            records_processed_date = NOW(),
            records_processor_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $records_user_id, $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $ownerId = $research['user_id'];
        $recordsName = $this->getEmployeeName($records_user_id);
        
        // 1. Notify Exec Dir (type_id = 6)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 6");
        if ($result) {
            while ($execDir = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been processed by Records ($recordsName) and is ready for your approval.";
                $this->insertNotification($execDir['id'], $message, $research_id, 'approved');
            }
        }
        
        // 2. Notify Researcher
        $message = "Your research '{$title}' has been processed by Records ($recordsName) and is now with Executive Director.";
        $this->insertNotification($ownerId, $message, $research_id, 'approved');
        
        // 3. Notify Section Head (type_id = 2)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
        if ($result) {
            while ($secHead = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been processed by Records ($recordsName) and is now with Executive Director.";
                $this->insertNotification($secHead['id'], $message, $research_id, 'approved');
            }
        }
        
        // 4. Notify Division Chief (type_id = 3)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 3");
        if ($result) {
            while ($divChief = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been processed by Records ($recordsName) and is now with Executive Director.";
                $this->insertNotification($divChief['id'], $message, $research_id, 'approved');
            }
        }
        
        // 5. Notify Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been processed by Records ($recordsName).";
                $this->insertNotification($admin['id'], $message, $research_id, 'approved');
            }
        }
        
        // Log activity
        $this->insertLog($records_user_id, "Processed research '{$title}' in Records");
    }
    
    return $success;
}
// Send research to Records (by Div Chief)
public function sendToRecords($research_id, $div_chief_id)
{
    $research_id = (int)$research_id;
    $div_chief_id = (int)$div_chief_id;
    
    // Get Div Chief's type_id
    $divChiefTypeId = $this->getUserTypeId($div_chief_id);
    
    // Mark as sent to Records AND update decided_by to Div Chief
    $stmt = $this->con->prepare("
        UPDATE research 
        SET sent_to_records = 1,
            desisyon_id = ?,
            decided_by_role = ?
        WHERE id = ?
    ");
    $stmt->bind_param("iii", $div_chief_id, $divChiefTypeId, $research_id);
    $stmt->execute();
    $stmt->close();
    
    // Get research info
    $research = $this->getResearchById($research_id);
    $title = $research['title'];
    $ownerId = $research['user_id'];
    $divChiefName = $this->getEmployeeName($div_chief_id);
    
    // 1. Notify Records (type_id = 5)
    $result = $this->con->query("SELECT id FROM employee WHERE type_id = 5");
    if ($result) {
        while ($records = $result->fetch_assoc()) {
            $message = "Research '{$title}' has been sent by Division Chief ($divChiefName) for processing.";
            $this->insertNotification($records['id'], $message, $research_id, 'approved');
        }
    }
    
    // 2. Notify Researcher (owner)
    $message = "Your research '{$title}' has been forwarded to Records by Division Chief ($divChiefName).";
    $this->insertNotification($ownerId, $message, $research_id, 'approved');
    
    // 3. Notify Section Head (type_id = 2)
    $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
    if ($result) {
        while ($secHead = $result->fetch_assoc()) {
            $message = "Research '{$title}' has been forwarded to Records by Division Chief ($divChiefName).";
            $this->insertNotification($secHead['id'], $message, $research_id, 'approved');
        }
    }
    
    // 4. Notify Admin (type_id = 4)
    $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
    if ($result) {
        while ($admin = $result->fetch_assoc()) {
            $message = "Research '{$title}' has been forwarded to Records by Division Chief ($divChiefName).";
            $this->insertNotification($admin['id'], $message, $research_id, 'approved');
        }
    }
    
    // Log activity
    $this->insertLog($div_chief_id, "Sent research '{$title}' to Records");
    
    return true;
}


// Exec Dir rejects research
public function rejectByExecDir($research_id, $exec_user_id, $comment)
{
    $research_id = (int)$research_id;
    $exec_user_id = (int)$exec_user_id;
    $comment = $this->con->real_escape_string($comment);
    
    // Mark as rejected by exec and reset records flag, set decided_by_role
    $stmt = $this->con->prepare("
        UPDATE research 
        SET rejected_by_exec = 1,
            exec_reject_comment = ?,
            processed_by_records = 0,
            desisyon_id = ?,
            decided_by_role = 6
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $comment, $exec_user_id, $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $ownerId = $research['user_id'];
        $execName = $this->getEmployeeName($exec_user_id);
        
        // 1. Notify Records (type_id = 5)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 5");
        if ($result) {
            while ($records = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been rejected by Exec Dir ($execName). Please process and forward.";
                $this->insertNotification($records['id'], $message, $research_id, 'approved');
            }
        }
        
        // 2. Notify researcher
        // $ownerMessage = "Your research '{$title}' has been rejected by Executive Director. It will be forwarded for revision after Records processing.";
        // $this->insertNotification($ownerId, $ownerMessage, $research_id, 'approved');
        
        // 3. Notify Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been rejected by Executive Director ($execName).";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, 'approved');
            }
        }
        
        // Log activity
        $this->insertLog($exec_user_id, "Rejected research '{$title}'");
    }
    
    return $success;
}
// Records forwards rejected research
// Records forwards rejected research
 
public function forwardRejectedResearch($research_id, $records_user_id)
{
    $research_id = (int)$research_id;
    $records_user_id = (int)$records_user_id;
    
    // Change status to Revision (3), mark as processed, set decided_by_role = 6 (Exec Dir)
    $stmt = $this->con->prepare("
        UPDATE research 
        SET status_id = 3,
            decided_by_role = 6,
            processed_by_records = 1,
            rejected_by_exec = 0
        WHERE id = ?
    ");
    $stmt->bind_param("i", $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $ownerId = $research['user_id'];
        $recordsName = $this->getEmployeeName($records_user_id);
        
        // Notify EVERYONE (Researcher, Section Head, Division Chief, Exec Dir, Admin)
        
        // 1. Researcher
        $message = "Your research '{$title}' (rejected by Exec Dir) has been forwarded by Records. Please revise and resubmit.";
        $this->insertNotification($ownerId, $message, $research_id, 'revised');
        
        // 2. Section Head (type_id = 2)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
        if ($result) {
            while ($secHead = $result->fetch_assoc()) {
                $message = "Research '{$title}' (rejected by Exec Dir) has been forwarded by Records for revision.";
                $this->insertNotification($secHead['id'], $message, $research_id, 'revised');
            }
        }
        
        // 3. Division Chief (type_id = 3)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 3");
        if ($result) {
            while ($divChief = $result->fetch_assoc()) {
                $message = "Research '{$title}' (rejected by Exec Dir) has been forwarded by Records for revision.";
                $this->insertNotification($divChief['id'], $message, $research_id, 'revised');
            }
        }
        
        // 4. Exec Dir (type_id = 6) - for tracking
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 6");
        if ($result) {
            while ($execDir = $result->fetch_assoc()) {
                $message = "Research '{$title}' that you rejected has been forwarded by Records for revision.";
                $this->insertNotification($execDir['id'], $message, $research_id, 'revised');
            }
        }
        
        // 5. Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $message = "Research '{$title}' (rejected by Exec Dir) has been forwarded by Records ($recordsName) for revision.";
                $this->insertNotification($admin['id'], $message, $research_id, 'revised');
            }
        }
        
        // Log activity
        $this->insertLog($records_user_id, "Forwarded rejected research '{$title}' from Exec Dir");
    }
    
    return $success;
}

// Cancel research by Section Head or Division Chief
public function cancelResearch($research_id, $user_id, $user_type_id, $comment)
{
    $research_id = (int)$research_id;
    $user_id = (int)$user_id;
    $user_type_id = (int)$user_type_id;
    $comment = $this->con->real_escape_string($comment);
    
    // Update research to cancelled status
    $stmt = $this->con->prepare("
        UPDATE research 
        SET status_id = 4,
            desisyon_id = ?,
            decided_by_role = ?,
            comment = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("iisi", $user_id, $user_type_id, $comment, $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $ownerId = $research['user_id'];
        $cancellerName = $this->getEmployeeName($user_id);
        
        // 1. Notify researcher
        // $message = "Your research '{$title}' has been cancelled by {$cancellerName}. Reason: {$comment}";
        // $this->insertNotification($ownerId, $message, $research_id, 'cancelled');
        
        // 2. Notify Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been cancelled by {$cancellerName}.";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, 'cancelled');
            }
        }
        
        // 3. If cancelled by Div Chief (type_id = 3), notify Section Head too
        if ($user_type_id == 3) {
            $result = $this->con->query("SELECT id FROM employee WHERE type_id = 2");
            if ($result) {
                while ($secHead = $result->fetch_assoc()) {
                    $message = "Research '{$title}' has been cancelled by Division Chief ({$cancellerName}).";
                    $this->insertNotification($secHead['id'], $message, $research_id, 'cancelled');
                }
            }
        }
        
        // Log activity
        $this->insertLog($user_id, "Cancelled research '{$title}'");
    }
    
    return $success;
}


// Exec Dir cancels research (goes through Records first)
public function cancelByExecDir($research_id, $exec_user_id, $comment)
{
    $research_id = (int)$research_id;
    $exec_user_id = (int)$exec_user_id;
    $comment = $this->con->real_escape_string($comment);
    
    // Mark for Records to process
    $stmt = $this->con->prepare("
        UPDATE research 
        SET cancelled_by_exec = 1,
            exec_cancel_comment = ?,
            processed_by_records = 0,
            desisyon_id = ?,
            decided_by_role = 6
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $comment, $exec_user_id, $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $ownerId = $research['user_id'];
        $execName = $this->getEmployeeName($exec_user_id);
        
        // 1. Notify Records (type_id = 5)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 5");
        if ($result) {
            while ($records = $result->fetch_assoc()) {
                $message = "Research '{$title}' has been cancelled by Exec Dir ({$execName}). Please process and forward.";
                $this->insertNotification($records['id'], $message, $research_id, 'approved');
            }
        }
        
        // 2. Notify researcher
        $ownerMessage = "Your research '{$title}' has been cancelled by Executive Director. It will be finalized after Records processing.";
        $this->insertNotification($ownerId, $ownerMessage, $research_id, 'approved');
        
        // 3. Notify Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' has been cancelled by Executive Director ($execName).";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, 'approved');
            }
        }
        
        // Log activity
        $this->insertLog($exec_user_id, "Cancelled research '{$title}'");
    }
    
    return $success;
}
// Records forwards cancelled research
public function forwardCancelledResearch($research_id, $records_user_id)
{
    $research_id = (int)$research_id;
    $records_user_id = (int)$records_user_id;
    
    // Change status to Cancelled (4), mark as processed, set decided_by_role = 6 (Exec Dir)
    $stmt = $this->con->prepare("
        UPDATE research 
        SET status_id = 4,
            decided_by_role = 6,
            processed_by_records = 1,
            cancelled_by_exec = 0
        WHERE id = ?
    ");
    $stmt->bind_param("i", $research_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Get research info
        $research = $this->getResearchById($research_id);
        $title = $research['title'];
        $recordsName = $this->getEmployeeName($records_user_id);
        
        // Notify EVERYONE (Researcher, Sec Head, Div Chief, Exec Dir, Admin)
        
        // 1. Researcher
        $ownerId = $research['user_id'];
        $message = "Your research '{$title}' has been cancelled by Executive Director.";
        $this->insertNotification($ownerId, $message, $research_id, 'cancelled');
        
        // 2. Section Head + Division Chief
        $result = $this->con->query("SELECT id FROM employee WHERE type_id IN (2, 3)");
        if ($result) {
            while ($user = $result->fetch_assoc()) {
                $message = "Research '{$title}' (cancelled by Exec Dir) has been forwarded by Records.";
                $this->insertNotification($user['id'], $message, $research_id, 'cancelled');
            }
        }
        
        // 3. Exec Dir (type_id = 6) - for tracking
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 6");
        if ($result) {
            while ($execDir = $result->fetch_assoc()) {
                $message = "Research '{$title}' that you cancelled has been forwarded by Records.";
                $this->insertNotification($execDir['id'], $message, $research_id, 'cancelled');
            }
        }
        
        // 4. Admin (type_id = 4)
        $result = $this->con->query("SELECT id FROM employee WHERE type_id = 4");
        if ($result) {
            while ($admin = $result->fetch_assoc()) {
                $adminMessage = "Research '{$title}' (cancelled by Exec Dir) has been forwarded by Records ($recordsName).";
                $this->insertNotification($admin['id'], $adminMessage, $research_id, 'cancelled');
            }
        }
        
        // Log activity
        $this->insertLog($records_user_id, "Forwarded cancelled research '{$title}' from Exec Dir");
    }
    
    return $success;
}
// Mark a single notification as read by ID
public function markNotificationAsRead($notification_id)
{
    $notification_id = (int)$notification_id;
    $stmt = $this->con->prepare("UPDATE notifications SET status = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}
}
