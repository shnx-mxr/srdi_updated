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
                                        <?php if ($type_id == 1): ?><th>Action</th><?php endif; ?>
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

                                            <!-- Status Column -->
                                            <td>
                                                <?php
                                                // Check if rejected by Exec Dir
                                                if (isset($research['rejected_by_exec']) && $research['rejected_by_exec'] == 1) {
                                                    echo '<span class="badge bg-danger">Rejected by Exec Dir</span>';
                                                    if (!empty($research['exec_reject_comment'])) {
                                                        echo '<br><small class="text-muted mt-1 d-block">Reason: ' . htmlspecialchars($research['exec_reject_comment']) . '</small>';
                                                    }
                                                } else {
                                                    // Show who revised it
                                                    $decidedByRole = $research['decided_by_role'] ?? 0;
                                                    $revisedBy = match($decidedByRole) {
                                                        2 => 'Section Head',
                                                        3 => 'Division Chief',
                                                        6 => 'Exec Director',
                                                        default => 'Unknown'
                                                    };
                                                    echo '<span class="badge bg-warning">Revision</span>';
                                                    echo '<br><small class="text-muted">By: ' . $revisedBy . '</small>';
                                                }
                                                ?>
                                            </td>

                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>
                                            
                                            <?php if ($type_id == 1): ?>
                                                <!-- Researcher can edit and resubmit -->
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                        data-bs-target="#editModal<?= $research['id'] ?>">
                                                        <i class="fas fa-edit"></i> Edit & Resubmit
                                                    </button>

                                                    <!-- Edit Modal -->
                                                    <div class="modal fade" id="editModal<?= $research['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <form method="POST" action="edit_research_process.php" enctype="multipart/form-data">
                                                                <input type="hidden" name="research_id" value="<?= $research['id'] ?>">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Edit & Resubmit Research</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="alert alert-info">
                                                                            <strong>Note:</strong> After you submit, this research will go back to <strong>Section Head</strong> for re-approval.
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label>Title:</label>
                                                                            <input type="text" name="title" class="form-control"
                                                                                value="<?= htmlspecialchars($research['title']) ?>" required>
                                                                        </div>
                                                                    <div class="mb-3">
    <label>Members:</label>
    <div id="member-container-<?= $research['id'] ?>">
        <?php 
        $currentMembers = !empty($research['member']) ? explode(", ", $research['member']) : [];
        $employees = $db->getEmployees();
        
        if (!empty($currentMembers)) {
            foreach ($currentMembers as $index => $currentMember) {
                $isFirst = $index === 0;
        ?>
            <div class="input-group mb-2 member-input">
                <select name="members[]" class="form-select" required>
                    <option value="" disabled>Select Member</option>
                    <?php foreach ($employees as $emp):
                        $fullName = $emp['firstname'] . ' ' . $emp['lastname'];
                        $selected = trim($fullName) === trim($currentMember) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($fullName) ?>" <?= $selected ?>><?= htmlspecialchars($fullName) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$isFirst): ?>
                    <button class="btn btn-danger remove-member-modal" type="button">-</button>
                <?php endif; ?>
            </div>
        <?php }
        } else { ?>
            <div class="input-group mb-2 member-input">
                <select name="members[]" class="form-select" required>
                    <option value="" disabled selected>Select Member</option>
                    <?php foreach ($employees as $emp):
                        $fullName = $emp['firstname'] . ' ' . $emp['lastname'];
                    ?>
                        <option value="<?= htmlspecialchars($fullName) ?>"><?= htmlspecialchars($fullName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php } ?>
    </div>
    <!-- + button is now OUTSIDE the container -->
    <button class="btn btn-success btn-sm mt-1 add-member-modal" type="button" data-research-id="<?= $research['id'] ?>">
        + Add Member
    </button>
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
    <input type="file" name="revised_pdf" class="form-control" accept="application/pdf" id="revised_pdf_<?= $research['id'] ?>">
    <?php if (!empty($research['revised_pdf'])): ?>
        <small class="text-muted">Current: <a href="research/<?= htmlspecialchars($research['revised_pdf']) ?>" target="_blank">View PDF</a></small>
        <input type="hidden" name="has_existing_pdf" value="1">
    <?php else: ?>
        <small class="text-danger">* PDF file is required</small>
        <input type="hidden" name="has_existing_pdf" value="0">
    <?php endif; ?>
</div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success">Resubmit to Section Head</button>
                                                                    </div>
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