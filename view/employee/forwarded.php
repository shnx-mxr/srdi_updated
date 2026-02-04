<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];

// Get all research for the user based on type
$researchList = $db->getResearchForUser($user_id, $type_id);

// Filter: Show research that went beyond current role (for tracking)
// Filter: Show research that went beyond current role (for tracking)
$researchList = array_filter($researchList, function ($r) use ($type_id, $user_id) {
    // Only show approved research (status_id = 2)
    if ($r['status_id'] != 2) return false;
    
    // Type 1 (Researcher): sees their own research that went to Records
    if ($type_id == 1) {
        return $r['user_id'] == $user_id && $r['sent_to_records'] == 1;
    }
    
    // Type 2 (Section Head): sees all research that went to Records
    if ($type_id == 2) {
        return $r['sent_to_records'] == 1;
    }
    
    // Type 3 (Div Chief): sees research that was sent to Records (for tracking)
    if ($type_id == 3) {
        return $r['sent_to_records'] == 1;
    }
    
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
    
    // Add status description
    if ($research['processed_by_records'] == 1) {
        $researchList[$key]['stage'] = 'Processed by Records';
    } else {
        $researchList[$key]['stage'] = 'At Records';
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
                <h1 class="mt-4">Forwarded Research (Tracking)</h1>
                <p class="text-muted">Research that has moved beyond your approval stage</p>

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
                        <div class="card-header"><i class="fas fa-history"></i> Forwarded Research</div>
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
                                        <th>Current Stage</th>
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
                                                <?php else: ?>N/A<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($research['processed_by_records'] == 1): ?>
                                                    <span class="badge bg-info">Processed by Records</span><br>
                                                    <small class="text-muted">With Executive Director</small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">At Records</span><br>
                                                    <small class="text-muted">Awaiting processing</small>
                                                <?php endif; ?>
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
                    <p>No forwarded research found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>