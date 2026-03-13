<?php
session_start();
require_once "../../controller/Main.php";

// Access control: only employees can upload research
if (!isset($_SESSION['user_id']) || $_SESSION['type_id'] != 1) {
    header("Location: ../../auth/login.php");
    exit;
}

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();
$message = '';
$messageType = '';

// Fetch notifications for navbar
$notifications = $db->getNotifications($user_id, $type_id, 10);
$unreadCount = 0;
foreach ($notifications as $notif) {
    if ($notif['status'] == 0) $unreadCount++;
}

// Fetch all employees for member selection
$employees = $db->getEmployees();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $membersArray = $_POST['members'] ?? [];
    $members = implode(", ", $membersArray);
    $startDate = $_POST['startDate'] ?? date('Y-m-d');
    $endDate = $_POST['endDate'] ?? date('Y-m-d');
    $file = $_FILES['research_file'] ?? null;
    $selected_type_id = $_POST['type_id']; // use selected type

    if ($title && $description && !empty($membersArray) && $file && $file['error'] == 0 && $startDate && $endDate && $selected_type_id) {
        $targetDir = __DIR__ . "/research/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $filename = time() . '_' . basename($file['name']);
        $targetFile = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            if ($db->uploadResearch($title, $description, $members, $filename, $user_id, $selected_type_id, $startDate, $endDate, $fullname)) {
                // Map type text for alert
                $typeText = $selected_type_id == 1 ? "Mulberry" : ($selected_type_id == 2 ? "Post Cocoon" : "Silkworm");
                $message = "Research uploaded successfully! (Type: $typeText)";
                $messageType = 'success';
            } else {
                $message = "Database error while saving research.";
                $messageType = 'error';
            }
        } else {
            $message = "Failed to upload file.";
            $messageType = 'error';
        }
    } else {
        $message = "Please fill in all fields, select a file, provide dates, and choose a research type.";
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'partials/header.php'; ?>

<body class="sb-nav-fixed">
    <?php include 'partials/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'partials/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between my-4">
    <h1 class="fw-semibold mb-0">Upload Research</h1>
    <span class="text-muted small">Submit new research for review and processing</span>
</div>
<hr class="mt-2 mb-4">


                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-file-upload me-1"></i> Upload Research</div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Research Type</label>
                                <select name="type_id" class="form-select" required>
                                    <option value="" disabled selected>Select Research Type</option>
                                    <option value="1">Mulberry</option>
                                    <option value="2">Post Cocoon</option>
                                    <option value="3">Silkworm</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Member(s)</label>
                                <div id="member-container">
                                    <div class="input-group mb-2 member-input">
                                        <select name="members[]" class="form-select" required>
                                            <option value="" disabled selected>Select Member</option>
                                            <?php foreach ($employees as $emp):
                                                $fullName = $emp['firstname'] . ' ' . $emp['lastname'];
                                                if ($fullName === $fullname) continue;
                                            ?>
                                                <option value="<?= htmlspecialchars($fullName) ?>"><?= htmlspecialchars($fullName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-success add-member" type="button">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="startDate" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="endDate" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Upload File</label>
                                <input type="file" name="research_file" class="form-control" accept=".pdf" multiple requiredny>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <!-- Bootstrap JS & SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Dynamic Member Add/Remove -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('member-container');
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('add-member')) {
                    const inputGroup = e.target.closest('.member-input');
                    const newInput = inputGroup.cloneNode(true);
                    newInput.querySelector('select').selectedIndex = 0;

                    const btn = newInput.querySelector('button');
                    btn.textContent = '-';
                    btn.classList.replace('btn-success', 'btn-danger');
                    btn.classList.replace('add-member', 'remove-member');

                    container.appendChild(newInput);
                } else if (e.target.classList.contains('remove-member')) {
                    e.target.closest('.member-input').remove();
                }
            });
        });
    </script>

    <?php if ($message): ?>
        <script>
            Swal.fire({
                icon: '<?= $messageType ?>',
                title: '<?= $messageType === "success" ? "Success" : "Oops!" ?>',
                text: '<?= $message ?>',
                confirmButtonColor: '#3085d6',
            });
        </script>
    <?php endif; ?>
</body>

</html>