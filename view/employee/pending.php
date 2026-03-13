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
$branch = $_SESSION['branch'] ?? null;
$researchList = $db->getResearchForUser($user_id, $type_id, $branch);

// Filter: Only Section Head and Admin see pending
$researchList = array_filter($researchList, function ($r) use ($user_id, $type_id) {
    // Only show pending research (status_id = 1)
    if ($r['status_id'] != 1) return false;
    
    // Type 1 (Researcher): sees only their own pending research
    if ($type_id == 1) {
        return $r['user_id'] == $user_id;
    }
    
    // Type 2 (Section Head): sees ALL pending research
    if ($type_id == 2) {
        return true;
    }
    
    // Type 4 (Admin): sees ALL pending research (view-only)
    if ($type_id == 4) {
        return true;
    }
    
    // Type 3 (Div Chief), Type 5 (Records), Type 6 (Exec Dir): do NOT see pending
    return false;
});

// Handle Section Head actions (Approve/Revise/Cancel)
if ($type_id == 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['research_id'])) {
    $researchId = intval($_POST['research_id']);
    
    // Approve
    if (isset($_POST['update_status']) && $_POST['update_status'] == 2) {
        $db->updateResearchStatusExtended($researchId, 2, $user_id, null, null);
        $alert = ['icon' => 'success', 'title' => 'Approved!', 'text' => 'Research approved successfully.', 'redirect' => 'pending.php'];
    }
    
    // Revise
    elseif (isset($_POST['update_status']) && $_POST['update_status'] == 3) {
        $comment = $_POST['comment'] ?? null;
        $complianceFile = null;

        if (empty($comment)) {
            $alert = ['icon' => 'error', 'title' => 'Comment Required', 'text' => 'Please enter a comment.', 'redirect' => 'pending.php'];
        } elseif (!isset($_FILES['compliance']) || $_FILES['compliance']['error'] !== 0) {
            $alert = ['icon' => 'error', 'title' => 'Compliance Required', 'text' => 'Please upload a compliance PDF file.', 'redirect' => 'pending.php'];
        } else {
            $targetDir = __DIR__ . "/compliance/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $originalName = basename($_FILES['compliance']['name']);
            $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
            $filename = time() . '_' . $safeName;
            $targetFile = $targetDir . $filename;

            $fileType = mime_content_type($_FILES['compliance']['tmp_name']);
            if ($fileType !== 'application/pdf') {
                $alert = ['icon' => 'error', 'title' => 'Invalid File', 'text' => 'Compliance file must be a PDF.', 'redirect' => 'pending.php'];
            } elseif (!move_uploaded_file($_FILES['compliance']['tmp_name'], $targetFile)) {
                $alert = ['icon' => 'error', 'title' => 'Upload Failed', 'text' => 'Unable to upload compliance PDF.', 'redirect' => 'pending.php'];
            } else {
                $complianceFile = $filename;
                $db->updateResearchStatusExtended($researchId, 3, $user_id, $comment, $complianceFile);
                $alert = ['icon' => 'success', 'title' => 'Revised!', 'text' => 'Research sent for revision.', 'redirect' => 'pending.php'];
            }
        }
    }
    
    // Cancel
    elseif (isset($_POST['cancel_research'])) {
        $comment = $_POST['cancel_comment'] ?? '';
        if (empty($comment)) {
            $alert = ['icon' => 'error', 'title' => 'Comment Required', 'text' => 'Please provide a reason for cancellation.', 'redirect' => 'pending.php'];
        } else {
            $db->cancelResearch($researchId, $user_id, $type_id, $comment);
            $alert = ['icon' => 'success', 'title' => 'Cancelled!', 'text' => 'Research has been cancelled.', 'redirect' => 'pending.php'];
        }
    }
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
            <div class="d-flex align-items-center justify-content-between my-4">
    <h1 class="fw-semibold mb-0">Pending Research</h1>
    <span class="text-muted small">For review & approval</span>
</div>
<hr class="mt-2 mb-4">


                <!-- Type Filter -->
                <!-- <form method="GET" class="mb-3">
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
                </form> -->

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
                   <div class="card-body table-responsive">
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
        <?php if ($research['research_type'] === 'program' && $research['program_id']): ?>
            <!-- PROGRAM ROW -->
            <?php 
            $programDetails = $db->getProgramDetails($research['program_id']);
            $projectCount = count($programDetails['projects'] ?? []);
            $studyCount = 0;
            foreach ($programDetails['projects'] ?? [] as $proj) {
                $studyCount += count($proj['studies'] ?? []);
            }
            ?>
            <tr class="table-primary">
                <td>
                    <strong><i class="fas fa-folder-open"></i> PROGRAM:</strong> 
                    <?= htmlspecialchars($research['title']) ?>
                    <br>
                    <small class="text-muted">
                        <?= $projectCount ?> Project(s), <?= $studyCount ?> Study(ies)
                    </small>
                </td>
                <td><?= htmlspecialchars($research['team_leader']) ?></td>
                <td colspan="3">
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#programModal<?= $research['id'] ?>">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
                <td>-</td>
                <td><span class="badge bg-warning">Pending</span></td>
                <td><?= htmlspecialchars($research['decided_by']) ?></td>
                <td>Multiple</td>
                <?php if ($type_id == 2): ?>
                    <td>
                        <form method="POST" class="d-inline mb-1">
                            <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                            <button type="submit" name="update_status" value="2" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form><br>
                        
                        <button type="button" class="btn btn-warning btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#reviseModal<?= $research['id'] ?>">
                            <i class="fas fa-edit"></i> Revise
                        </button><br>
                        
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $research['id'] ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>

                        <!-- Revise Modal (same as before) -->
                        <div class="modal fade" id="reviseModal<?= $research['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" enctype="multipart/form-data" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Revise Research</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                        <input type="hidden" name="update_status" value="3">
                                        <div class="mb-3">
                                            <label>Comment (Required)</label>
                                            <textarea class="form-control" name="comment" rows="3" required></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label>Upload Compliance PDF (Required)</label>
                                            <input type="file" class="form-control" name="compliance" accept="application/pdf" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-warning">Submit Revision</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Cancel Modal (same as before) -->
                        <div class="modal fade" id="cancelModal<?= $research['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Cancel Research</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                        <input type="hidden" name="cancel_research" value="1">
                                        <div class="mb-3">
                                            <label>Reason for Cancellation (Required)</label>
                                            <textarea class="form-control" name="cancel_comment" rows="3" required placeholder="Explain why this research is being cancelled..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-danger">Cancel Research</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            
            <!-- Program Details Modal -->
            <div class="modal fade" id="programModal<?= $research['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-folder-open"></i> Program Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h4><?= htmlspecialchars($research['title']) ?></h4>
                            <hr>
                            
                            <?php if (!empty($programDetails['sustainable_goals'])): ?>
                            <h5>SDGs Selected:</h5>
                            <p><?= implode(', ', json_decode($programDetails['sustainable_goals'], true)) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($programDetails['hnrda_area'])): ?>
                            <h5>HNRDA Area:</h5>
                            <p><?= htmlspecialchars($programDetails['hnrda_area']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($programDetails['hnrda_sector'])): ?>
                            <h5>HNRDA Sector:</h5>
                            <p><?= htmlspecialchars($programDetails['hnrda_sector']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($programDetails['focus_rdi_agenda'])): ?>
                            <h5>Focus RDI Agenda:</h5>
                            <p><?= implode(', ', json_decode($programDetails['focus_rdi_agenda'], true)) ?></p>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <h5>Projects & Studies:</h5>
                            <?php foreach ($programDetails['projects'] ?? [] as $project): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <strong>Project:</strong> <?= htmlspecialchars($project['project_title']) ?>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($project['studies'] ?? [] as $study): ?>
                                            <div class="mb-3 p-3 border">
                                                <h6><strong>Study:</strong> <?= htmlspecialchars($study['study_title']) ?></h6>
                                                <p><strong>Section:</strong> <?= htmlspecialchars($study['section']) ?></p>
                                                <p><strong>Members:</strong> <?= htmlspecialchars($study['study_members']) ?></p>
                                                <p><strong>Duration:</strong> <?= $study['start_date'] ?> to <?= $study['end_date'] ?></p>
                                                <p><strong>Description:</strong> <?= htmlspecialchars($study['description']) ?></p>
                                                <?php if ($study['attachment_path']): ?>
                                                    <a href="study_attachments/<?= $study['attachment_path'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-file-pdf"></i> View Study PDF
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            
                            <h5>Program Attachments:</h5>
                            <?php foreach ($programDetails['attachments'] ?? [] as $attachment): ?>
                                <a href="program_attachments/<?= $attachment['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-1">
                                    <i class="fas fa-file-pdf"></i> <?= ucwords(str_replace('_', ' ', $attachment['file_type'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($research['research_type'] === 'project' && $research['program_id']): ?>
            <!-- PROJECT ROW (similar to program but simpler) -->
            <?php 
            $programDetails = $db->getProgramDetails($research['program_id']);
            $project = $programDetails['projects'][0] ?? null;
            $studyCount = $project ? count($project['studies'] ?? []) : 0;
            ?>
            <tr class="table-info">
                <td>
                    <strong><i class="fas fa-project-diagram"></i> PROJECT:</strong> 
                    <?= htmlspecialchars($research['title']) ?>
                    <br>
                    <small class="text-muted"><?= $studyCount ?> Study(ies)</small>
                </td>
                <td><?= htmlspecialchars($research['team_leader']) ?></td>
                <td colspan="3">
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#projectModal<?= $research['id'] ?>">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
                <td>-</td>
                <td><span class="badge bg-warning">Pending</span></td>
                <td><?= htmlspecialchars($research['decided_by']) ?></td>
                <td><?= $project['studies'][0]['section'] ?? 'Multiple' ?></td>
                <?php if ($type_id == 2): ?>
                    <td>
                        <!-- Same action buttons as program -->
                        <form method="POST" class="d-inline mb-1">
                            <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                            <button type="submit" name="update_status" value="2" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form><br>
                        <button type="button" class="btn btn-warning btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#reviseModal<?= $research['id'] ?>">
                            <i class="fas fa-edit"></i> Revise
                        </button><br>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $research['id'] ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <!-- Modals same as above -->
                    </td>
                <?php endif; ?>
            </tr>
            
            <!-- Project Modal (simpler version of program modal) -->
            
        <?php else: ?>
            <!-- REGULAR STUDY ROW (YOUR EXISTING CODE) -->
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
                        <a href="revised_research/<?= htmlspecialchars($research['revised_pdf']) ?>" target="_blank">Revised PDF</a>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if (!empty($research['compliance'])): ?>
                        <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                    <?php else: ?> N/A <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-warning">Pending</span>
                </td>
                <td><?= htmlspecialchars($research['decided_by']) ?></td>
                <td><?= htmlspecialchars($research['type_name']) ?></td>

                <?php if ($type_id == 2): ?>
                    <!-- YOUR EXISTING ACTION BUTTONS AND MODALS -->
                    <td>
                        <form method="POST" class="d-inline mb-1">
                            <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                            <button type="submit" name="update_status" value="2" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form><br>
                        
                        <button type="button" class="btn btn-warning btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#reviseModal<?= $research['id'] ?>">
                            <i class="fas fa-edit"></i> Revise
                        </button><br>
                        
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $research['id'] ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>

                        <!-- YOUR EXISTING MODALS -->
                        <div class="modal fade" id="reviseModal<?= $research['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" enctype="multipart/form-data" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Revise Research</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                        <input type="hidden" name="update_status" value="3">
                                        <div class="mb-3">
                                            <label>Comment (Required)</label>
                                            <textarea class="form-control" name="comment" rows="3" required></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label>Upload Compliance PDF (Required)</label>
                                            <input type="file" class="form-control" name="compliance" accept="application/pdf" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-warning">Submit Revision</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="modal fade" id="cancelModal<?= $research['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Cancel Research</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                        <input type="hidden" name="cancel_research" value="1">
                                        <div class="mb-3">
                                            <label>Reason for Cancellation (Required)</label>
                                            <textarea class="form-control" name="cancel_comment" rows="3" required placeholder="Explain why this research is being cancelled..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-danger">Cancel Research</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endif; ?>
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