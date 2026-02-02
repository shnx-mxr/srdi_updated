<?php
session_start();
require_once "../../controller/Main.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();
$alert = null;

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];

// Handle type filter from GET
$filterType = isset($_GET['filter_type']) ? intval($_GET['filter_type']) : 0;

// Get all research for user based on type
$researchList = $db->getResearchForUser($user_id, $type_id);

// Filter research based on type and Records processing stage
$researchList = array_filter($researchList, function ($r) use ($type_id, $user_id) {
    if ($type_id == 2) {
        // Section Head: sees approved research (status = 2)
        return in_array($r['status_id'], [2]);
    } elseif ($type_id == 3) {
        // Div Chief: sees approved research NOT YET sent to Records
        return $r['status_id'] == 2 && $r['processed_by_records'] == 0;
    } elseif ($type_id == 5) {
        // Records: sees approved research NOT YET processed OR rejected by Exec Dir
        return ($r['status_id'] == 2 && $r['processed_by_records'] == 0) || ($r['rejected_by_exec'] == 1 && $r['processed_by_records'] == 0);
    } elseif ($type_id == 6) {
        // Exec Dir: sees approved research ALREADY processed by Records
        return $r['status_id'] == 2 && $r['processed_by_records'] == 1;
    } elseif ($type_id == 1) {
        // Researcher: sees their own approved research
        return $r['user_id'] == $user_id && $r['status_id'] == 2;
    } elseif ($type_id == 4) {
        // Admin: sees all approved research
        return $r['status_id'] == 2;
    }
    return false;
});

// Apply type_id filter if selected
if ($filterType && in_array($filterType, array_keys($typeNames))) {
    $researchList = array_filter($researchList, function ($r) use ($filterType) {
        return $r['type_id'] == $filterType;
    });
}

// Handle actions from Div Chief, Records, or Exec Dir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['research_id'])) {
    $researchId = intval($_POST['research_id']);
    
    // Div Chief: Send to Records
    if ($type_id == 3 && isset($_POST['send_to_records'])) {
        $db->sendToRecords($researchId, $user_id);
        $alert = ['icon' => 'success', 'title' => 'Sent!', 'text' => 'Research sent to Records successfully.', 'redirect' => 'approved.php'];
    }
    
    // Records: Mark as Processed
    elseif ($type_id == 5 && isset($_POST['mark_processed'])) {
        // Check if it's rejected by exec
        $research = $db->getResearchById($researchId);
        if ($research['rejected_by_exec'] == 1) {
            // Forward rejected research
            $db->forwardRejectedResearch($researchId, $user_id);
            $alert = ['icon' => 'success', 'title' => 'Forwarded!', 'text' => 'Rejected research forwarded successfully.', 'redirect' => 'approved.php'];
        } else {
            // Normal processing
            $db->markProcessedByRecords($researchId, $user_id);
            $alert = ['icon' => 'success', 'title' => 'Processed!', 'text' => 'Research marked as processed.', 'redirect' => 'approved.php'];
        }
    }
    
    // Exec Dir: Publish
    elseif ($type_id == 6 && isset($_POST['publish'])) {
        $db->updateResearchStatusExtended($researchId, 5, $user_id, null, null);
        $alert = ['icon' => 'success', 'title' => 'Published!', 'text' => 'Research published successfully.', 'redirect' => 'approved.php'];
    }
    
    // Exec Dir: Reject
    elseif ($type_id == 6 && isset($_POST['reject_by_exec'])) {
        $comment = $_POST['reject_comment'] ?? '';
        if (empty($comment)) {
            $alert = ['icon' => 'error', 'title' => 'Comment Required', 'text' => 'Please provide a reason for rejection.', 'redirect' => 'approved.php'];
        } else {
            $db->rejectByExecDir($researchId, $user_id, $comment);
            $alert = ['icon' => 'success', 'title' => 'Rejected!', 'text' => 'Research sent back to Records for processing.', 'redirect' => 'approved.php'];
        }
    }
    
    // Type 3: Revise (existing functionality)
    elseif ($type_id == 3 && isset($_POST['update_status']) && $_POST['update_status'] == 4) {
        $comment = $_POST['comment'] ?? null;
        $complianceFile = null;
        
        if (empty($comment)) {
            $alert = ['icon' => 'error', 'title' => 'Comment Required', 'text' => 'Please enter a comment.', 'redirect' => 'approved.php'];
        } elseif (!isset($_FILES['compliance']) || $_FILES['compliance']['error'] != 0) {
            $alert = ['icon' => 'error', 'title' => 'Compliance Required', 'text' => 'Please upload a compliance PDF.', 'redirect' => 'approved.php'];
        } else {
            $targetDir = __DIR__ . "/compliance/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $originalName = basename($_FILES['compliance']['name']);
            $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $originalName);
            $filename = time() . '_' . $safeName;
            $targetFile = $targetDir . $filename;

            $fileType = mime_content_type($_FILES['compliance']['tmp_name']);
            if ($fileType != 'application/pdf') {
                $alert = ['icon' => 'error', 'title' => 'Invalid File', 'text' => 'Compliance file must be a PDF.', 'redirect' => 'approved.php'];
            } elseif (move_uploaded_file($_FILES['compliance']['tmp_name'], $targetFile)) {
                $complianceFile = $filename;
                $db->updateResearchStatusExtended($researchId, 4, $user_id, $comment, $complianceFile);
                $alert = ['icon' => 'success', 'title' => 'Updated!', 'text' => 'Research status updated successfully.', 'redirect' => 'approved.php'];
            } else {
                $alert = ['icon' => 'error', 'title' => 'Upload Failed', 'text' => 'Unable to upload compliance PDF.', 'redirect' => 'approved.php'];
            }
        }
    }
}

// Add team leader and decided by names
foreach ($researchList as $key => $research) {
    $researchList[$key]['team_leader'] = $db->getEmployeeName($research['user_id']);
    $researchList[$key]['decided_by'] = $research['desisyon_id'] ? $db->getEmployeeName($research['desisyon_id']) : '-';
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
                <h1 class="mt-4">Approved Research</h1>

                <!-- Filter Form -->
                <form method="GET" class="mb-3 row g-2 align-items-center">
                    <div class="col-auto">
                        <select name="filter_type" class="form-select">
                            <option value="0">-- Filter by Type --</option>
                            <?php foreach ($typeNames as $id => $name): ?>
                                <option value="<?= $id ?>" <?= ($filterType == $id) ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="approved.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>

                <?php if ($researchList): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-list"></i> Approved Research</div>
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
                                        <?php if (in_array($type_id, [3, 5, 6])) echo '<th>Action</th>'; ?>
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
                                                    <a href="research/<?= htmlspecialchars($research['filePath']) ?>" target="_blank">View Research</a>
                                                <?php else: ?>N/A<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($research['compliance'])): ?>
                                                    <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                                                <?php else: ?>N/A<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusLabel = $research['status_id'] == 2 ? 'Approved' : ($research['status_id'] == 4 ? 'Revision' : 'Unknown');
                                                $badge = $research['status_id'] == 2 ? 'success' : 'info';
                                                echo "<span class='badge bg-$badge'>$statusLabel</span>";
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($typeNames[$research['type_id']] ?? 'Unknown') ?></td>

                                            <?php if ($type_id == 3): ?>
                                                <!-- Division Chief Actions -->
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                        <button type="submit" name="send_to_records" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-paper-plane"></i> Send to Records
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reviseModal<?= $research['id'] ?>">
                                                        <i class="fas fa-edit"></i> Revise
                                                    </button>

                                                    <!-- Revise Modal -->
                                                    <div class="modal fade" id="reviseModal<?= $research['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <form method="POST" enctype="multipart/form-data" class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Revise Research</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                                    <input type="hidden" name="update_status" value="4">
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
                                                
                                            <?php elseif ($type_id == 5): ?>
                                                <!-- Records Actions -->
                                                <td>
                                                    <?php if ($research['rejected_by_exec'] == 1): ?>
                                                        <span class="badge bg-danger mb-2">Rejected by Exec Dir</span><br>
                                                    <?php endif; ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                        <button type="submit" name="mark_processed" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> <?= $research['rejected_by_exec'] == 1 ? 'Forward to Revision' : 'Mark as Processed' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                
                                            <?php elseif ($type_id == 6): ?>
                                                <!-- Exec Dir Actions -->
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                        <button type="submit" name="publish" class="btn btn-success btn-sm">
                                                            <i class="fas fa-globe"></i> Publish
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $research['id'] ?>">
                                                        <i class="fas fa-times-circle"></i> Reject
                                                    </button>

                                                    <!-- Reject Modal -->
                                                    <div class="modal fade" id="rejectModal<?= $research['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <form method="POST" class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Reject Research</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                                    <input type="hidden" name="reject_by_exec" value="1">
                                                                    <div class="mb-3">
                                                                        <label>Reason for Rejection (Required)</label>
                                                                        <textarea class="form-control" name="reject_comment" rows="4" required placeholder="Explain why this research is being rejected..."></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Reject Research</button>
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
                    <p>No approved research found.</p>
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
            }).then(() => window.location.href = 'approved.php');
        </script>
    <?php endif; ?>
</body>

</html>