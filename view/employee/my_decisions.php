<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

// Only Exec Dir can access this page
if ($type_id != 6) {
    header("Location: dashboard.php");
    exit;
}

$db = new db();

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];

// Get all research for the user
$researchList = $db->getResearchForUser($user_id, $type_id);

// Filter: Show all research decided by Exec Dir (decided_by_role = 6)
$researchList = array_filter($researchList, function ($r) use ($user_id) {
    return $r['decided_by_role'] == 6 && $r['desisyon_id'] == $user_id;
});

// Apply type filter if requested
$filterType = $_GET['type_id'] ?? '';
if ($filterType && in_array($filterType, [1, 2, 3])) {
    $researchList = array_filter($researchList, function ($r) use ($filterType) {
        return $r['type_id'] == $filterType;
    });
}

// Add team leader and current status info
foreach ($researchList as $key => $research) {
    $researchList[$key]['team_leader'] = $db->getEmployeeName($research['user_id']);
    $researchList[$key]['type_name'] = $typeNames[$research['type_id']] ?? 'Unknown';
    
    // Add current status description
    $statusMap = [
        1 => ['name' => 'Re-submitted (Pending)', 'badge' => 'bg-warning', 'desc' => 'At Section Head'],
        2 => ['name' => 'Approved', 'badge' => 'bg-success', 'desc' => 'Moving through approval chain'],
        3 => ['name' => 'In Revision', 'badge' => 'bg-warning', 'desc' => 'Researcher is fixing'],
        4 => ['name' => 'Cancelled', 'badge' => 'bg-dark', 'desc' => 'Research terminated'],
        5 => ['name' => 'Published', 'badge' => 'bg-primary', 'desc' => 'Research published']
    ];
    
    $status = $statusMap[$research['status_id']] ?? ['name' => 'Unknown', 'badge' => 'bg-secondary', 'desc' => ''];
    $researchList[$key]['status_info'] = $status;
    
    // Add your decision
    if ($research['rejected_by_exec'] == 1 || $research['status_id'] == 3) {
        $researchList[$key]['my_decision'] = 'Revised';
        $researchList[$key]['my_comment'] = $research['exec_reject_comment'] ?? '-';
    } elseif ($research['cancelled_by_exec'] == 1 || $research['status_id'] == 4) {
        $researchList[$key]['my_decision'] = 'Cancelled';
        $researchList[$key]['my_comment'] = $research['exec_cancel_comment'] ?? '-';
    } elseif ($research['status_id'] == 5) {
        $researchList[$key]['my_decision'] = 'Published';
        $researchList[$key]['my_comment'] = '-';
    } else {
        $researchList[$key]['my_decision'] = 'Approved';
        $researchList[$key]['my_comment'] = '-';
    }
}

// Sort by updated_at DESC (most recent first)
usort($researchList, function($a, $b) {
    return strtotime($b['updated_at']) - strtotime($a['updated_at']);
});
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
                <h1 class="mt-4">My Decisions (Tracking)</h1>
                <p class="text-muted">All research you have reviewed and decided on</p>

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
                        <div class="card-header"><i class="fas fa-history"></i> My Decisions History</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Team Leader</th>
                                        <th>My Decision</th>
                                        <th>My Comment</th>
                                        <th>Current Status</th>
                                        <th>Date Decided</th>
                                        <th>Type</th>
                                        <th>Files</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($researchList as $research): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($research['title']) ?></td>
                                            <td><?= htmlspecialchars($research['team_leader']) ?></td>
                                            <td>
                                                <?php
                                                $decisionBadge = match($research['my_decision']) {
                                                    'Published' => 'bg-primary',
                                                    'Revised' => 'bg-warning',
                                                    'Cancelled' => 'bg-dark',
                                                    default => 'bg-success'
                                                };
                                                ?>
                                                <span class="badge <?= $decisionBadge ?>"><?= $research['my_decision'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($research['my_comment'] != '-'): ?>
                                                    <small><?= htmlspecialchars($research['my_comment']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $research['status_info']['badge'] ?>">
                                                    <?= $research['status_info']['name'] ?>
                                                </span>
                                                <?php if ($research['status_info']['desc']): ?>
                                                    <br><small class="text-muted"><?= $research['status_info']['desc'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($research['updated_at'])): ?>
                                                    <?= date('M d, Y', strtotime($research['updated_at'])) ?>
                                                    <br><small class="text-muted"><?= date('h:i A', strtotime($research['updated_at'])) ?></small>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>
                                            <td>
                                                <?php if (!empty($research['filePath'])): ?>
                                                    <a href="research/<?= htmlspecialchars($research['filePath']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-pdf"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No decisions found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>