<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();
$alert = null;

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];

// Get all research for the user based on type
$researchList = $db->getResearchForUser($user_id, $type_id);

// Filter only pending research (status_id = 1)
$researchList = array_filter($researchList, function ($r) use ($user_id, $type_id) {
    if ($r['status_id'] != 1) return false; // only pending
    if ($type_id == 1) return $r['user_id'] == $user_id; // type 1 sees only their own
    return true; // type 2,3,4 sees all
});

// Handle Approve/Revise only for type_id = 2
if ($type_id == 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['research_id'])) {
    $researchId = intval($_POST['research_id']);
    $newStatus = intval($_POST['update_status']);
    $comment = $_POST['comment'] ?? null;
    $complianceFile = null;

    if ($newStatus === 3) {

        if (empty($comment)) {
            $alert = [
                'icon' => 'error',
                'title' => 'Comment Required',
                'text' => 'Please enter a comment.',
                'redirect' => 'pending.php'
            ];
            return;
        }

        if (!isset($_FILES['compliance']) || $_FILES['compliance']['error'] !== 0) {
            $alert = [
                'icon' => 'error',
                'title' => 'Compliance Required',
                'text' => 'Please upload a compliance PDF file.',
                'redirect' => 'pending.php'
            ];
            return;
        }

        $targetDir = __DIR__ . "/compliance/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $originalName = basename($_FILES['compliance']['name']);
        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
        $filename = time() . '_' . $safeName;
        $targetFile = $targetDir . $filename;

        $fileType = mime_content_type($_FILES['compliance']['tmp_name']);
        if ($fileType !== 'application/pdf') {
            $alert = [
                'icon' => 'error',
                'title' => 'Invalid File',
                'text' => 'Compliance file must be a PDF.',
                'redirect' => 'pending.php'
            ];
            return;
        }

        if (!move_uploaded_file($_FILES['compliance']['tmp_name'], $targetFile)) {
            $alert = [
                'icon' => 'error',
                'title' => 'Upload Failed',
                'text' => 'Unable to upload compliance PDF.',
                'redirect' => 'pending.php'
            ];
            return;
        }

        $complianceFile = $filename;
    }

    // ================= UPDATE STATUS =================
    $db->updateResearchStatusExtended(
        $researchId,
        $newStatus,
        $user_id,
        $comment,
        $complianceFile
    );

    $alert = [
        'icon' => 'success',
        'title' => 'Updated!',
        'text' => 'Research status updated successfully.',
        'redirect' => 'pending.php'
    ];
}

// Add team leader, decided by names, and type name
foreach ($researchList as $key => $research) {
    $researchList[$key]['team_leader'] = $db->getEmployeeName($research['user_id']);
    $researchList[$key]['decided_by'] = $research['desisyon_id'] ? $db->getEmployeeName($research['desisyon_id']) : '-';
    $researchList[$key]['type_name'] = $typeNames[$research['type_id']] ?? 'Unknown';
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
                <h1 class="mt-4">Pending Research</h1>

                <!-- Type Filter -->
                <form method="GET" class="mb-3">
                    <label>Filter by Type:</label>
                    <select name="type_id" class="form-select w-auto d-inline-block">
                        <option value="">All</option>
                        <?php foreach ($typeNames as $id => $name): ?>
                            <option value="<?= $id ?>" <?= (isset($_GET['type_id']) && $_GET['type_id'] == $id) ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </form>

                <?php
                // Apply type filter if selected
                if (isset($_GET['type_id']) && in_array($_GET['type_id'], array_keys($typeNames))) {
                    $filterType = $_GET['type_id'];
                    $researchList = array_filter($researchList, function ($r) use ($filterType) {
                        return $r['type_id'] == $filterType;
                    });
                }
                ?>

                <?php if ($researchList): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-list"></i> Pending Research</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Team Leader</th>
                                        <th>Members</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Research File</th>
                                        <th>Compliance File</th>
                                        <th>Status</th>
                                        <th>Decided By</th>
                                        <th>Type</th>
                                        <?php if ($type_id == 2) echo '<th>Action</th>'; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($researchList as $research): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($research['title']) ?></td>
                                            <td><?= htmlspecialchars($research['team_leader']) ?></td>
                                            <td><?= htmlspecialchars($research['member']) ?></td>
                                            <td><?= htmlspecialchars($research['startDate']) ?></td>
                                            <td><?= htmlspecialchars($research['endDate']) ?></td>
                                            <td>
                                                <?php if (!empty($research['filePath'])): ?>
                                                    <a href="research/<?= htmlspecialchars($research['filePath']) ?>" target="_blank">Original PDF</a>
                                                <?php else: ?> N/A <?php endif; ?>

                                                <?php if (!empty($research['revised_pdf'])): ?>
                                                    <br>
                                                    <a href="research/<?= htmlspecialchars($research['revised_pdf']) ?>" target="_blank">Revised PDF</a>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($research['compliance'])): ?>
                                                    <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($research['status_id'] == 3)
                                                    echo '<span class="badge bg-info">Revision</span>';
                                                else
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>

                                            <?php if ($type_id == 2): ?>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <form method="POST">
                                                            <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                            <button type="submit" name="update_status" value="2" class="btn btn-success btn-sm">Approve</button>
                                                        </form>
                                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reviseModal<?= $research['id'] ?>">Revision</button>
                                                    </div>

                                                    <!-- Revise Modal -->
                                                    <div class="modal fade" id="reviseModal<?= $research['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <form method="POST" enctype="multipart/form-data" class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Revision Research</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                                    <input type="hidden" name="update_status" value="3">
                                                                    <div class="mb-3">
                                                                        <label>Comment (Required)</label>
                                                                        <textarea class="form-control" name="comment" required></textarea>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label>Upload Compliance PDF (Required)</label>
                                                                        <input type="file" class="form-control" name="compliance" accept="application/pdf" required>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="submit" class="btn btn-danger">Submit Revision</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No pending research found.</p>
                <?php endif; ?>
            </main>

            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($alert): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
                icon: '<?= $alert['icon'] ?>',
                title: '<?= $alert['title'] ?>',
                text: '<?= $alert['text'] ?>',
                confirmButtonColor: '#3085d6'
            }).then(() => {
                window.location.href = 'pending.php';
            });
        </script>
    <?php endif; ?>
</body>

</html>