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

// Filter only Revised research (status_id = 3)
$researchList = array_filter($researchList, function ($r) {
    return $r['status_id'] == 3;
});

// Apply type filter if requested
$filterType = $_GET['type_id'] ?? '';
if ($filterType && in_array($filterType, [1, 2, 3])) {
    $researchList = array_filter($researchList, function ($r) use ($filterType) {
        return $r['type_id'] == $filterType;
    });
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
                <h1 class="mt-4">Revision Research</h1>

                <!-- Type Filter -->
                <form method="GET" class="mb-3">
                    <label>Filter by Type:</label>
                    <select name="type_id" class="form-select w-auto d-inline-block">
                        <option value="">All</option>
                        <?php foreach ($typeNames as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $filterType == $id ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </form>

                <?php if ($researchList): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-list"></i> Revised Research</div>
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
                                        <th>Comment</th>
                                        <th>Status</th>
                                        <th>Decided By</th>
                                        <th>Type</th>
                                        <th>Action</th>
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
                                                    <br><a href="research/<?= htmlspecialchars($research['revised_pdf']) ?>" target="_blank">Revised PDF</a>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($research['compliance'])): ?>
                                                    <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($research['comment'] ?? '-') ?></td>

                                            <!-- 👇 UPDATED STATUS COLUMN -->
                                            <td>
                                                <?php
                                                if (isset($research['rejected_by_exec']) && $research['rejected_by_exec'] == 1) {
                                                    echo '<span class="badge bg-danger">Rejected by Exec Dir</span>';
                                                    if (!empty($research['exec_reject_comment'])) {
                                                        echo '<br><small class="text-muted mt-1 d-block">Reason: ' . htmlspecialchars($research['exec_reject_comment']) . '</small>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-warning">Revision</span>';
                                                }
                                                ?>
                                            </td>

                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>
                                            <td>
                                                <?php if ($type_id == 1): ?>
                                                    <!-- Edit button triggers modal -->
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                        data-bs-target="#editModal<?= $research['id'] ?>">Edit
                                                    </button>

                                                    <!-- Edit Modal -->
                                                    <div class="modal fade" id="editModal<?= $research['id'] ?>" tabindex="-1"
                                                        aria-labelledby="editModalLabel<?= $research['id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <form method="POST" action="edit_research_process.php" enctype="multipart/form-data">
                                                                <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="editModalLabel<?= $research['id'] ?>">Edit Research</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label>Title:</label>
                                                                            <input type="text" name="title" class="form-control"
                                                                                value="<?= htmlspecialchars($research['title']) ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label>Members:</label>
                                                                            <input type="text" name="member" class="form-control"
                                                                                value="<?= htmlspecialchars($research['member']) ?>">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label>Start Date:</label>
                                                                            <input type="date" name="startDate" class="form-control"
                                                                                value="<?= htmlspecialchars($research['startDate']) ?>">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label>End Date:</label>
                                                                            <input type="date" name="endDate" class="form-control"
                                                                                value="<?= htmlspecialchars($research['endDate']) ?>">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label>Upload Revised PDF:</label>
                                                                            <input type="file" name="revised_pdf" class="form-control" accept="application/pdf">
                                                                            <?php if (!empty($research['revised_pdf'])): ?>
                                                                                <small>Current revised PDF: <a href="research/<?= htmlspecialchars($research['revised_pdf']) ?>" target="_blank">View</a></small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                                                    </div>
                                                                </div>
                                                            </form>

                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No revised research found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>