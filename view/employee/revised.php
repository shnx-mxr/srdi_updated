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

// Filter: Show based on WHO revised AND user role
$researchList = array_filter($researchList, function ($r) use ($type_id, $user_id) {
    // Only show revised research (status_id = 3)
    if ($r['status_id'] != 3) return false;
    
    $decidedByRole = $r['decided_by_role'] ?? 0;
    
    // Type 1 (Researcher): sees their own revised research
    if ($type_id == 1) {
        return $r['user_id'] == $user_id;
    }
    
    // Type 2 (Section Head): 
    // - Sees research they revised (decided_by_role = 2)
    // - OR research revised by Div Chief or Exec Dir (decided_by_role = 3 or 6)
    if ($type_id == 2) {
        return in_array($decidedByRole, [2, 3, 6]);
    }
    
    // Type 3 (Div Chief):
    // - Sees research they revised (decided_by_role = 3)
    // - OR research revised by Exec Dir (decided_by_role = 6)
    if ($type_id == 3) {
        return in_array($decidedByRole, [3, 6]);
    }
    
    // Type 4 (Admin): sees all revised research
    if ($type_id == 4) {
        return true;
    }
    
   // Type 6 (Exec Dir): sees ONLY revisions forwarded by Records (decided_by_role = 6)
    if ($type_id == 6) {
        return $decidedByRole == 6;
    }
    // Type 5 (Records): does NOT see revised research
    return false;
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
   <div class="d-flex align-items-center justify-content-between my-4">
    <h1 class="fw-semibold mb-0">Revision Research</h1>
    
    <span class="text-muted small">Research returned for revision and compliance</span>
</div>
<hr class="mt-2 mb-4">


                <!-- Type Filter -->
                <!-- <form method="GET" class="mb-3">
                    <label>Filter by Type:</label>
                    <select name="type_id" class="form-select w-auto d-inline-block">
                        <option value="">All</option>
                        <?php foreach ($typeNames as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $filterType == $id ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </form> -->

                <?php if ($researchList): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-list"></i> Revised Research</div>
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
                                        <th>Comment</th>
                                        <th>Status</th>
                                        <th>Decided By</th>
                                        <th>Type</th>
                                        <?php if ($type_id == 1): ?><th>Action</th><?php endif; ?>
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
                    <p>No revised research found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <script>
// Dynamic Member Add/Remove in Edit Modal
document.addEventListener('click', function(e) {
    // Add member
    if (e.target.classList.contains('add-member-modal')) {
        const researchId = e.target.getAttribute('data-research-id');
        const container = document.getElementById('member-container-' + researchId);
        const inputGroup = e.target.closest('.member-input');
        const newInput = inputGroup.cloneNode(true);
        
        // Reset selection
        newInput.querySelector('select').selectedIndex = 0;
        
        // Change button to remove
        const btn = newInput.querySelector('button');
        btn.textContent = '-';
        btn.classList.replace('btn-success', 'btn-danger');
        btn.classList.replace('add-member-modal', 'remove-member-modal');
        btn.removeAttribute('data-research-id');
        
        container.appendChild(newInput);
    }
    
    // Remove member
    if (e.target.classList.contains('remove-member-modal')) {
        e.target.closest('.member-input').remove();
    }
});

document.addEventListener('click', function(e) {
    // Add member
    if (e.target.classList.contains('add-member-modal')) {
        const researchId = e.target.getAttribute('data-research-id');
        const container = document.getElementById('member-container-' + researchId);
        
        // Clone first select for options
        const firstSelect = container.querySelector('select');
        const newSelect = firstSelect.cloneNode(true);
        newSelect.selectedIndex = 0;
        
        // Create remove button
        const removeBtn = document.createElement('button');
        removeBtn.textContent = '-';
        removeBtn.type = 'button';
        removeBtn.classList.add('btn', 'btn-danger', 'remove-member-modal');
        
        // Create new row
        const newDiv = document.createElement('div');
        newDiv.classList.add('input-group', 'mb-2', 'member-input');
        newDiv.appendChild(newSelect);
        newDiv.appendChild(removeBtn);
        
        container.appendChild(newDiv);
    }
    
    // Remove member
    if (e.target.classList.contains('remove-member-modal')) {
        e.target.closest('.member-input').remove();
    }
});
// Validate revised PDF before submit
document.querySelectorAll('form[action="edit_research_process.php"]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const hasExisting = form.querySelector('input[name="has_existing_pdf"]').value;
        const fileInput = form.querySelector('input[name="revised_pdf"]');
        
        // If no existing PDF and no new file selected, prevent submit
        if (hasExisting === '0' && !fileInput.files.length) {
            e.preventDefault();
            alert('Please upload a revised PDF file before submitting.');
            fileInput.focus();
        }
    });
});
</script>
</body>

</html>