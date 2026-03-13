<?php
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require_once "../../controller/Main.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

// Session variables
$fullname = $_SESSION['fullname'] ?? 'User';
$type_id = $_SESSION['type_id'] ?? 0;
$session_user_id = $_SESSION['user_id'] ?? 0;

$db = new db();


$typeDisplayNames = [
    1 => 'Researcher',
    2 => 'Section Head',
    3 => 'Division Chief',
    4 => 'Admin',
    5 => 'Records',
    6 => 'Executive Head'
];

$cleanFullname = preg_replace(
    '/^(Researcher|Section Head|Division Chief|Admin)\s+/i',
    '',
    $fullname
);
// Use the mapping instead of direct DB value
$typeName = $typeDisplayNames[$type_id] ?? 'Unknown Type';

// Status mapping: change 3 => 'Revision'
$statusText = [1 => 'Pending', 2 => 'Approved', 3 => 'Revision', 4 => 'Cancelled', 5 => 'Published'];
$bgColors = [
    'pending' => 'secondary',
    'approved' => 'success',
    'revised' => 'info', // will use 'info' for Revision
    'cancelled' => 'warning',
    'published' => 'primary'
];

// Get branch from session
$branch = $_SESSION['branch'] ?? null;

// Determine which research to fetch based on role
$statusCountsRaw = $db->getResearchStatusCounts($session_user_id, $type_id, $branch);
$allResearch = $db->getAllResearch($session_user_id, $type_id, $branch);
$monthlyCounts = $db->getMonthlyResearchCounts(date('Y'), $session_user_id, $type_id, $branch);

// 👇 ADD THESE LINES HERE (PAGINATION SETTINGS)
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Replace 'revised' counts key with 'revision'
$statusCountsRaw['revision'] = $statusCountsRaw['revised'] ?? 0;
unset($statusCountsRaw['revised']);
// Replace 'revised' counts key with 'revision'
$statusCountsRaw['revision'] = $statusCountsRaw['revised'] ?? 0;
unset($statusCountsRaw['revised']);

// Prepare status counts
$statusMap = [1 => 'pending', 2 => 'approved', 3 => 'revision', 4 => 'cancelled', 5 => 'published'];
$statusCounts = [];
foreach ($statusMap as $id => $name) {
    $statusCounts[$name] = $statusCountsRaw[$name] ?? 0;
}

// Prepare monthly data
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyData = [];
for ($i = 1; $i <= 12; $i++) {
    // Always include all 12 months, use 0 if no data
    $monthlyData[] = isset($monthlyCounts[$i]) ? (int)$monthlyCounts[$i] : 0;
}

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];
?>

<?php include 'partials/header.php'; ?>

<body class="sb-nav-fixed">
    <?php include 'partials/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'partials/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                 <div class="d-flex align-items-center justify-content-between mt-4 position-relative mb-2">
    <h1 class="fw-bold mb-0 position-relative d-inline-block">
        Dashboard
        <span class="position-absolute start-0 bottom-0 w-50" style="height:4px;background:#0d6efd;border-radius:2px"></span>
    </h1>
    <span class="text-muted small ms-3">Your overview of research submissions</span>
    
</div>
<hr>
                    <ol class="breadcrumb mb-4">
<li class="breadcrumb-item active">
    Hi, <strong><?= htmlspecialchars($typeName . ' ' . $cleanFullname) ?></strong>!
</li>

                    </ol>
<?php
// Get program/study counts
$programCount = 0;
$projectCount = 0;
$studyCount = 0;
foreach ($researchList as $r) {
    $researchType = $r['research_type'] ?? 'study'; // Default to study if column doesn't exist
    if ($researchType === 'program') {
        $programCount++;
    } elseif ($researchType === 'project') {
        $projectCount++;
    } else {
        $studyCount++;
    }
}
?>

<div class="alert alert-info">
    <strong>Research Overview:</strong> 
    <?= $programCount ?> Program(s), 
    <?= $projectCount ?> Project(s), 
    <?= $studyCount ?> Individual Study(ies)
</div>

<div class="alert alert-info">
    <strong>Research Overview:</strong> 
    <?= $programCount ?> Program(s), <?= $studyCount ?> Individual Study(ies)
</div>
                    <!-- Status Cards -->
                    <div class="row">
                        <?php foreach ($statusCounts as $key => $count):
                            $color = $bgColors[$key] ?? 'dark';
                        ?>
                            <div class="col-md-2">
                                <div class="card bg-<?= $color ?> text-white mb-4">
                                    <div class="card-body"><?= ucfirst($key) ?>: <?= $count ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?> 
                    </div>

              <!-- Monthly Chart -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-chart-bar me-2"></i> Monthly Research  (<?= date('Y') ?>)
    </div>
    <div class="card-body">
        <canvas id="monthlyChart" height="80"></canvas>
    </div>
</div>

            <!-- Research Table -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-table me-2"></i> Research List
    </div>
    <div class="card-body table-responsive">
        <?php 
        // Pagination logic
        $totalResearch = count($allResearch);
        $totalPages = ceil($totalResearch / $itemsPerPage);
        $paginatedResearch = array_slice($allResearch, $offset, $itemsPerPage);
        ?>
        
        <table class="table table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>File</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Member(s)</th>
                    <th>Research Leader</th>
                    <th>Compliance</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paginatedResearch as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= htmlspecialchars($r['description']) ?></td>
                        <td>
                            <?php if (!empty($r['filePath'])): ?>
                                <a href="research/<?= htmlspecialchars($r['filePath']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-pdf"></i> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['startDate']) ?></td>
                        <td><?= htmlspecialchars($r['endDate']) ?></td>
                        <td><?= htmlspecialchars($r['member']) ?></td>
                        <td>
                            <?= ($type_id == 1 && $r['user_id'] == $session_user_id)
                                ? '<span class="badge bg-success">You</span>'
                                : htmlspecialchars($r['leader_firstname'] . ' ' . $r['leader_lastname']) ?>
                        </td>
                        <td>
                            <?php if (!empty($r['compliance'])): ?>
                                <a href="compliance/<?= htmlspecialchars($r['compliance']) ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-pdf"></i> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['comment']) ?></td>
                        <td>
                            <?php
                            if ($r['status_id'] == 3) echo '<span class="badge bg-info">Revision</span>';
                            else echo '<span class="badge bg-' . ($bgColors[strtolower($statusText[$r['status_id']])] ?? 'secondary') . '">' . htmlspecialchars($statusText[$r['status_id']] ?? 'Unknown') . '</span>';
                            ?>
                        </td>
                        <td><?= htmlspecialchars($typeNames[$r['type_id']] ?? 'Unknown') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Research pagination">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Button -->
                    <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <p class="text-center text-muted mt-2 mb-0">
                Showing <?= $offset + 1 ?> to <?= min($offset + $itemsPerPage, $totalResearch) ?> of <?= $totalResearch ?> entries
            </p>
        <?php endif; ?>
    </div>
</div>

                </div>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    
    // Create gradient for bars
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(13, 110, 253, 0.8)');
    gradient.addColorStop(1, 'rgba(13, 110, 253, 0.4)');
    
    const monthlyChart = new Chart(ctx, {
        type: 'bar', // 👈 Changed back to bar
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Research Count',
                data: <?= json_encode($monthlyData) ?>,
                backgroundColor: gradient,
                borderColor: '#0d6efd',
                borderWidth: 2,
                borderRadius: 8, // 👈 Rounded corners
                barThickness: 40 // 👈 Bar width
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 13
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
</script>
</body>

</html>