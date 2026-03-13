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
// Get research using the new method
$branch = $_SESSION['branch'] ?? null;
$researchList = $db->getResearchForUser($user_id, $type_id, $branch);
// Filter: Show based on WHO cancelled AND user role (RULE 2 - Tracking visibility)
$researchList = array_filter($researchList, function ($r) use ($type_id, $user_id) {
    // Only show cancelled research (status_id = 4)
    if ($r['status_id'] != 4) return false;
    
    $decidedByRole = $r['decided_by_role'] ?? 0;
    
    // Type 1 (Researcher): sees their own cancelled research
    if ($type_id == 1) {
        return $r['user_id'] == $user_id;
    }
    
    // Type 2 (Section Head): 
    // - Sees research they cancelled (decided_by_role = 2)
    // - AND research cancelled by higher roles that went through them (decided_by_role = 3 or 6)
    if ($type_id == 2) {
        return in_array($decidedByRole, [2, 3, 6]);
    }
    
    // Type 3 (Div Chief):
    // - Sees research they cancelled (decided_by_role = 3)
    // - AND research cancelled by Exec Dir (decided_by_role = 6)
    if ($type_id == 3) {
        return in_array($decidedByRole, [3, 6]);
    }
    
    // Type 4 (Admin): sees all cancelled research
    if ($type_id == 4) {
        return true;
    }
    
    // Type 6 (Exec Dir): sees research they cancelled (decided_by_role = 6)
    if ($type_id == 6) {
        return $decidedByRole == 6;
    }
    
    // Type 5 (Records): does NOT see cancelled research
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
    <h1 class="fw-semibold mb-0">Cancelled Research</h1>
    <span class="text-muted small">Research that has been formally cancelled</span>
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
                        <div class="card-header"><i class="fas fa-list"></i> Cancelled Research</div>
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
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($research['compliance'])): ?>
                                                    <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Show cancel comment from exec or regular comment
                                                $cancelComment = $research['exec_cancel_comment'] ?? $research['comment'] ?? '-';
                                                echo htmlspecialchars($cancelComment);
                                                ?>
                                            </td>
                                            
                                            <!-- Status Column -->
                                            <td>
                                                <?php
                                                if (isset($research['cancelled_by_exec']) && $research['cancelled_by_exec'] == 1) {
                                                    echo '<span class="badge bg-danger">Cancelled by Exec Dir</span>';
                                                    if (!empty($research['exec_cancel_comment'])) {
                                                        echo '<br><small class="text-muted mt-1 d-block">Reason: ' . htmlspecialchars($research['exec_cancel_comment']) . '</small>';
                                                    }
                                                } else {
                                                    // Show who cancelled it
                                                    $decidedByRole = $research['decided_by_role'] ?? 0;
                                                    $cancelledBy = match($decidedByRole) {
                                                        2 => 'Section Head',
                                                        3 => 'Division Chief',
                                                        6 => 'Exec Director',
                                                        default => 'Unknown'
                                                    };
                                                    echo '<span class="badge bg-dark">Cancelled</span>';
                                                    echo '<br><small class="text-muted">By: ' . $cancelledBy . '</small>';
                                                }
                                                ?>
                                            </td>
                                            
                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No cancelled research found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>