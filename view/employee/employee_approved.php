<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();

// Only allow admin
if ($type_id != 4) {
    header("Location: ../../auth/login.php");
    exit;
}

// Fetch approved employees
$approvedEmployees = $db->getEmployeesByStatus(2); // 2 = Approved

$itemsPerPage = 10; // rows per page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch employees for current page
$approvedEmployees = $db->getEmployeesByStatus(2, $itemsPerPage, $offset);

// Total pages
$totalEmployees = $db->countEmployeesByStatus(2);
$totalPages = ceil($totalEmployees / $itemsPerPage);

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
                <h1 class="mt-4">Approved Employees</h1>

                <?php if ($approvedEmployees): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-users"></i> Approved Employees</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Address</th>
                                        <th>Type</th>
                                        <th>Branch</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvedEmployees as $emp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?></td>
                                            <td><?= htmlspecialchars($emp['email']) ?></td>
                                            <td><?= htmlspecialchars($emp['address']) ?></td>
                                            <td>
                                                <?php
                                                $typeNames = [
                                                    1 => 'Researcher',
                                                    2 => 'Section Head',
                                                    3 => 'Division Chief',
                                                    4 => 'Admin',
                                                    5 => 'Records',
                                                    6 => 'Executive Head'
                                                ];
                                                echo $typeNames[$emp['type_id']] ?? 'Unknown';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                // Branch only for Section Head (type_id = 2)
                                                if ($emp['type_id'] == 2) {
                                                    echo htmlspecialchars($emp['branch'] ?? '-');
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
        <li class="page-item <?= ($page == $currentPage) ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $page ?>"><?= $page ?></a>
        </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

                        </div>
                    </div>
                <?php else: ?>
                    <p>No approved employees found.</p>
                <?php endif; ?>

            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
